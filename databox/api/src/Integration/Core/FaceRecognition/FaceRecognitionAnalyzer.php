<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Traits\AssetAnnotationsInterface;
use App\Integration\IntegrationConfig;
use App\Integration\PusherTrait;
use App\Repository\Core\AttributeRepository;
use App\Service\Asset\FileFetcher;
use App\Service\Image\ImageDownscaler;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Detects the faces of an asset through the face-recognition service and writes them as values of
 * the configured entity attribute: one value per face (its person, box, confidence and embedding).
 * Each face is recognized among the faces of the workspace, or starts a new unknown person.
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
        private readonly AttributeRepository $attributeRepository,
        private readonly RenditionManager $renditionManager,
        private readonly FileFetcher $fileFetcher,
        private readonly FaceRecognitionClient $client,
        private readonly ImageDownscaler $imageDownscaler,
        private readonly FaceManager $faceManager,
        private readonly FaceRecognitionConfigResolver $configResolver,
        private readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    /**
     * Runs the detection on the asset (configured rendition, or source) and replaces its faces.
     *
     * @return Attribute[]|null the faces written, or null when the asset has no image to analyze
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

        $definition = $this->configResolver->getDefinition($config);
        $minConfidence = (float) $config['minConfidence'];
        $threshold = (float) $config['matchThreshold'];
        $previousFaces = $this->attributeRepository->findEmbeddingsOfAsset($definition->getId(), $asset->getId());
        $known = [];
        $loaded = false;

        $faces = [];
        $previousValues = [];
        foreach ($previousFaces as $previous) {
            $previousValues[(string) $previous->getValue()] = true;
        }

        $input = new AssetAttributeBatchUpdateInput();
        $delete = new AttributeActionInput();
        $delete->definitionId = $definition->getId();
        $delete->action = BatchAttributeManager::ACTION_DELETE;
        $delete->originVendor = FaceRecognitionIntegration::getName();
        $delete->originVendorContext = self::DATA_FACES;
        $input->actions[] = $delete;

        foreach ($result['faces'] as $detected) {
            if ((float) $detected['confidence'] < $minConfidence) {
                continue;
            }
            $box = self::normalizeBox($detected['box']);
            $vector = array_map('floatval', $detected['embedding']);

            $add = new AttributeActionInput();
            $add->definitionId = $definition->getId();
            $add->action = BatchAttributeManager::ACTION_ADD;
            $add->originVendor = FaceRecognitionIntegration::getName();
            $add->originVendorContext = self::DATA_FACES;
            $add->position = count($faces);
            $add->annotations = [[
                'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                'x' => $box['x'],
                'y' => $box['y'],
                'w' => $box['w'],
                'h' => $box['h'],
            ]];
            $add->embedding = ['m' => (string) $result['model'], 'v' => $vector];

            $same = $this->findSameFace($box, $previousFaces);
            if (null !== $same) {
                // The same face keeps its person: what a user said, or the person it was recognized as
                // (a new threshold is applied by the relink command, not by a re-detection)
                $add->value = $same->getValue();
                $add->origin = Attribute::ORIGIN_LABELS[$same->getOrigin()] ?? Attribute::ORIGIN_LABELS[Attribute::ORIGIN_MACHINE];
                $add->originUserId = $same->getOriginUserId();
                $add->confidence = $same->getConfidence();
                $known[] = ['value' => (string) $same->getValue(), 'origin' => $same->getOrigin(), 'vector' => $vector];
            } else {
                if (!$loaded) {
                    $known = [...$known, ...iterator_to_array($this->attributeRepository->iterateEmbeddings($definition->getId(), $asset->getId()), false)];
                    $loaded = true;
                }
                $match = $this->faceManager->findPerson($vector, $known, $threshold);
                if (null === $match) {
                    // The first face of a person nobody named: as sure as its detection
                    $person = $this->faceManager->createUnknownPerson($config);
                    $this->em->flush();
                    $match = ['value' => $person->getId(), 'confidence' => (float) $detected['confidence']];
                }
                $add->value = $match['value'];
                $add->origin = Attribute::ORIGIN_LABELS[Attribute::ORIGIN_MACHINE];
                $add->confidence = $match['confidence'];
                // The faces of this asset are known to the next ones (the same person twice in the image)
                $known[] = ['value' => $match['value'], 'origin' => Attribute::ORIGIN_MACHINE, 'vector' => $vector];
            }

            $input->actions[] = $add;
            $faces[] = $add;
        }

        $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input, null);

        $this->faceManager->removeOrphanUnknowns($this->configResolver->getEntityList($config), array_keys($previousValues));

        $this->triggerPush('asset-'.$asset->getId(), 'integration:'.FaceRecognitionIntegration::getName(), [
            'count' => count($faces),
        ], direct: true);

        return $this->attributeRepository->findEmbeddingsOfAsset($definition->getId(), $asset->getId());
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
     * @param array{x: float, y: float, w: float, h: float} $box
     * @param Attribute[]                                   $previousFaces
     */
    private function findSameFace(array $box, array $previousFaces): ?Attribute
    {
        $best = null;
        $bestIou = self::SAME_FACE_IOU;
        foreach ($previousFaces as $previous) {
            $previousBox = self::boxOf($previous);
            if (null === $previousBox) {
                continue;
            }
            $iou = FaceMatcher::boxIou($box, $previousBox);
            if ($iou >= $bestIou) {
                $best = $previous;
                $bestIou = $iou;
            }
        }

        return $best;
    }

    /**
     * The rectangle annotation of a face value.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function boxOf(Attribute $face): ?array
    {
        foreach ($face->getAssetAnnotations() ?? [] as $annotation) {
            if (AssetAnnotationsInterface::TYPE_RECTANGLE === ($annotation['type'] ?? null)) {
                return self::normalizeBox($annotation);
            }
        }

        return null;
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}
     */
    private static function normalizeBox(array $box): array
    {
        return [
            'x' => (float) $box['x'],
            'y' => (float) $box['y'],
            'w' => (float) $box['w'],
            'h' => (float) $box['h'],
        ];
    }
}
