<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFace;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Traits\AssetAnnotationsInterface;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationDataManager;
use App\Integration\PusherTrait;
use App\Repository\Core\AssetFaceRepository;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\FileFetcher;
use App\Service\Face\FaceMatcher;
use App\Service\Face\FaceRecognitionClient;
use App\Service\Image\ImageDownscaler;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Detects faces of an asset through the face-recognition service, recognizes them against the
 * faces identified by users in the workspace, then exposes the result as integration data
 * (for the asset view) and optionally as attribute values.
 */
final class FaceRecognitionAnalyzer
{
    use PusherTrait;

    final public const string DATA_FACES = 'faces';

    /**
     * Minimum overlap between a newly detected face and a previous one to consider they are the same face.
     */
    private const float SAME_FACE_IOU = 0.5;

    /**
     * Images heavier than this are re-encoded even when their dimensions fit (the service caps uploads at 10 MB).
     */
    private const int MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetFaceRepository $faceRepository,
        private readonly RenditionManager $renditionManager,
        private readonly FileFetcher $fileFetcher,
        private readonly FaceRecognitionClient $client,
        private readonly ImageDownscaler $imageDownscaler,
        private readonly FaceMatcher $matcher,
        private readonly IntegrationDataManager $dataManager,
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    /**
     * Runs the detection on the asset (configured rendition, or source) and replaces its known faces.
     *
     * @return array|null the faces summary, or null when the asset has no image to analyze
     */
    public function analyze(Asset $asset, IntegrationConfig $config): ?array
    {
        $file = $this->resolveFile($asset, $config);
        if (null === $file) {
            return null;
        }

        $path = $this->fileFetcher->getFile($file);
        // Faces are detected on a downscaled copy: lighter upload, and the boxes are normalized anyway
        $uploadPath = $this->imageDownscaler->downscale($path, (int) $config['maxImageSize'], self::MAX_UPLOAD_BYTES);
        try {
            $result = $this->client->detectFaces($uploadPath);
        } finally {
            if ($uploadPath !== $path) {
                @unlink($uploadPath);
            }
        }

        $minConfidence = (float) $config['minConfidence'];
        $threshold = (float) $config['matchThreshold'];
        $previousFaces = $this->faceRepository->findByAsset($asset->getId());
        $references = null;
        $carriedOver = [];

        $faces = [];
        foreach ($result['faces'] as $detected) {
            if ((float) $detected['confidence'] < $minConfidence) {
                continue;
            }

            $face = new AssetFace();
            $face->setAsset($asset);
            $face->setPosition(count($faces));
            $face->setBox($detected['box']);
            $face->setConfidence((float) $detected['confidence']);
            $face->setVector($detected['embedding']);
            $face->setModel($result['model']);
            $face->setDetails(array_filter([
                'age' => $detected['age'] ?? null,
                'gender' => $detected['gender'] ?? null,
                'landmarks' => $detected['landmarks'] ?? null,
            ], fn ($v): bool => null !== $v) ?: null);

            $previous = $this->findSameFace($face, $previousFaces);
            if (null !== $previous && $previous->isUserIdentified()) {
                // Keep what users have already told us about this face
                $face->setIdentity($previous->getIdentity(), AssetFace::IDENTITY_ORIGIN_USER, 1.0);
                $carriedOver[] = [$previous, $face];
            } elseif ($threshold > 0) {
                $references ??= $this->faceRepository->findReferenceFaces($asset->getWorkspaceId(), $asset->getId());
                $match = $this->matcher->findBestMatch($face->getVector(), $references, $threshold);
                if (null !== $match) {
                    $face->setIdentity($match['identity'], AssetFace::IDENTITY_ORIGIN_AUTO, $match['similarity'], $match['face']);
                }
            }

            $faces[] = $face;
        }

        foreach ($faces as $face) {
            $this->em->persist($face);
        }
        $this->em->flush();

        // Faces derived from a replaced reference now follow the new one (or lose their reference when it vanished)
        $replacements = [];
        foreach ($carriedOver as [$previous, $face]) {
            $replacements[$previous->getId()] = $face;
        }
        foreach ($previousFaces as $previous) {
            if ($previous->isUserIdentified()) {
                foreach ($this->faceRepository->findDerivedFaces($previous) as $derived) {
                    $derived->setReference($replacements[$previous->getId()] ?? null);
                }
            }
            $this->em->remove($previous);
        }
        $this->em->flush();

        return $this->syncAsset($asset, $faces, $config);
    }

    /**
     * Sets (or clears, with a null/empty identity) the identity of a face on behalf of a user.
     */
    public function identify(AssetFace $face, ?string $identity, IntegrationConfig $config): array
    {
        $identity = null !== $identity ? trim($identity) : null;
        if (null === $identity || '' === $identity) {
            $face->clearIdentity();
        } else {
            $face->setIdentity($identity, AssetFace::IDENTITY_ORIGIN_USER, 1.0);
        }
        $this->em->persist($face);
        $this->em->flush();

        $asset = $face->getAsset();

        return $this->syncAsset($asset, $this->faceRepository->findByAsset($asset->getId()), $config);
    }

    /**
     * Applies the identity of a user-identified face to the similar, non user-identified faces of the workspace.
     *
     * @return int the number of faces that received the identity
     */
    public function propagateIdentity(AssetFace $reference, IntegrationConfig $config): int
    {
        $threshold = (float) $config['matchThreshold'];
        if ($threshold <= 0 || !$reference->isUserIdentified()) {
            return 0;
        }

        $asset = $reference->getAsset();
        $identity = $reference->getIdentity();
        $vector = $reference->getVector();

        $count = 0;
        $affectedAssetIds = [];
        foreach ($this->faceRepository->iterateNonUserIdentifiedFaces($asset->getWorkspaceId(), $asset->getId()) as $candidate) {
            $similarity = FaceMatcher::cosineSimilarity($vector, $candidate->getVector());
            $derivedFromReference = $candidate->getReference()?->getId() === $reference->getId();
            $better = $derivedFromReference
                || !$candidate->hasIdentity()
                || $candidate->getIdentity() === $identity
                || $similarity > ($candidate->getIdentityConfidence() ?? 0.0);

            if ($better && ($derivedFromReference || $similarity >= $threshold)) {
                if ($candidate->getIdentity() !== $identity || !$derivedFromReference) {
                    ++$count;
                }
                $candidate->setIdentity($identity, AssetFace::IDENTITY_ORIGIN_AUTO, $similarity, $reference);
                $affectedAssetIds[$candidate->getAsset()->getId()] = true;
            } else {
                $this->em->detach($candidate);
            }
        }
        $this->em->flush();

        foreach (array_keys($affectedAssetIds) as $assetId) {
            $affectedAsset = $this->em->find(Asset::class, $assetId);
            if ($affectedAsset instanceof Asset) {
                $this->syncAsset($affectedAsset, $this->faceRepository->findByAsset($assetId), $config);
            }
        }

        return $count;
    }

    /**
     * Clears the identities that were derived from a face whose identity has been removed by a user.
     *
     * @return int the number of faces that lost their identity
     */
    public function revokeIdentity(AssetFace $reference, IntegrationConfig $config): int
    {
        $derived = $this->faceRepository->findDerivedFaces($reference);
        $affectedAssetIds = [];
        foreach ($derived as $face) {
            $face->clearIdentity();
            $affectedAssetIds[$face->getAsset()->getId()] = true;
        }
        $this->em->flush();

        foreach (array_keys($affectedAssetIds) as $assetId) {
            $affectedAsset = $this->em->find(Asset::class, $assetId);
            if ($affectedAsset instanceof Asset) {
                $this->syncAsset($affectedAsset, $this->faceRepository->findByAsset($assetId), $config);
            }
        }

        return count($derived);
    }

    /**
     * @param AssetFace[] $faces
     */
    public function buildSummary(array $faces): array
    {
        return [
            'faces' => array_map(fn (AssetFace $face): array => [
                'id' => $face->getId(),
                'box' => $face->getBox(),
                'confidence' => $face->getConfidence(),
                'identity' => $face->getIdentity(),
                'identityConfidence' => $face->getIdentityConfidence(),
                'identityOrigin' => $face->getIdentityOrigin(),
                'age' => $face->getDetails()['age'] ?? null,
                'gender' => $face->getDetails()['gender'] ?? null,
            ], $faces),
        ];
    }

    /**
     * Refreshes everything derived from the faces of an asset: integration data, attribute values and realtime event.
     *
     * @param AssetFace[] $faces
     */
    private function syncAsset(Asset $asset, array $faces, IntegrationConfig $config): array
    {
        $summary = $this->buildSummary($faces);

        $this->dataManager->storeData(
            $config->getWorkspaceIntegration(),
            null,
            $asset,
            self::DATA_FACES,
            json_encode($summary, JSON_THROW_ON_ERROR),
        );

        $this->saveIdentitiesToAttribute($asset, $faces, $config);

        $this->triggerPush('asset-'.$asset->getId(), 'integration:'.FaceRecognitionIntegration::getName(), [
            'count' => count($faces),
        ], direct: true);

        return $summary;
    }

    /**
     * @param AssetFace[] $faces
     */
    private function saveIdentitiesToAttribute(Asset $asset, array $faces, IntegrationConfig $config): void
    {
        $slug = $config['attribute'] ?? null;
        if (empty($slug)) {
            return;
        }

        $attrDef = $this->attributeDefinitionRepository
            ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $slug)
            ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $slug, $asset->getWorkspaceId()));

        if (!$attrDef->isMultiple()) {
            throw new \InvalidArgumentException(sprintf('Attribute "%s" must be multi-valued', $attrDef->getId()));
        }

        // One value per person, even when the same person appears several times in the image
        $identities = [];
        foreach ($faces as $face) {
            if (!$face->hasIdentity()) {
                continue;
            }
            $identity = $face->getIdentity();
            $box = $face->getBox();
            $identities[$identity] ??= [
                'confidence' => 0.0,
                'annotations' => [],
            ];
            $identities[$identity]['confidence'] = max($identities[$identity]['confidence'], $face->getIdentityConfidence() ?? 0.0);
            $identities[$identity]['annotations'][] = [
                'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                'x' => $box['x'],
                'y' => $box['y'],
                'w' => $box['w'],
                'h' => $box['h'],
            ];
        }

        $input = new AssetAttributeBatchUpdateInput();
        $i = new AttributeActionInput();
        $i->definitionId = $attrDef->getId();
        $i->action = BatchAttributeManager::ACTION_DELETE;
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendor = FaceRecognitionIntegration::getName();
        $i->originVendorContext = self::DATA_FACES;
        $input->actions[] = $i;

        foreach ($identities as $identity => $data) {
            $i = new AttributeActionInput();
            $i->action = BatchAttributeManager::ACTION_ADD;
            $i->originVendor = FaceRecognitionIntegration::getName();
            $i->originVendorContext = self::DATA_FACES;
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->definitionId = $attrDef->getId();
            $i->confidence = $data['confidence'];
            $i->value = (string) $identity;
            $i->annotations = $data['annotations'];
            $input->actions[] = $i;
        }

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $input,
            null
        );
    }

    /**
     * Returns the configured rendition file when it is an image, otherwise the source when it is an image.
     */
    private function resolveFile(Asset $asset, IntegrationConfig $config): ?File
    {
        $renditionName = $config['rendition'] ?? null;
        if (!empty($renditionName)) {
            $file = $this->renditionManager->getAssetRenditionByName($asset->getId(), $renditionName)?->getFile();
            if (null !== $file && FileUtil::isImageType($file->getType())) {
                return $file;
            }
        }

        $source = $asset->getSource();
        if (null !== $source && FileUtil::isImageType($source->getType())) {
            return $source;
        }

        return null;
    }

    /**
     * @param AssetFace[] $previousFaces
     */
    private function findSameFace(AssetFace $face, array $previousFaces): ?AssetFace
    {
        $best = null;
        $bestIou = self::SAME_FACE_IOU;
        foreach ($previousFaces as $previous) {
            $iou = FaceMatcher::boxIou($face->getBox(), $previous->getBox());
            if ($iou >= $bestIou) {
                $best = $previous;
                $bestIou = $iou;
            }
        }

        return $best;
    }
}
