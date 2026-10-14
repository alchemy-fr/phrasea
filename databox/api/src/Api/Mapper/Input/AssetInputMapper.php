<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetRelationshipInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRelationship;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\AssetManager;
use App\Service\Asset\Attribute\AssetNameFiller;
use App\Service\Asset\PickSourceRenditionManager;
use App\Service\Asset\StoryCoverManager;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: AssetInput::class)]
class AssetInputMapper extends AbstractFileInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;
    use AttributeInputTrait;

    final public const string CONTEXT_CREATION_MICRO_TIME = 'micro_time';

    public function __construct(
        private readonly PickSourceRenditionManager $pickSourceRenditionManager,
        private readonly AttributeInputMapper $attributeInputProcessor,
        private readonly AssetManager $assetManager,
        private readonly AssetRenditionInputMapper $renditionInputMapper,
        private readonly AssetNameFiller $assetNameFiller,
        private readonly StoryCoverManager $storyCoverManager,
    ) {
    }

    /**
     * @param AssetInput $data
     *
     * @return Asset
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        } elseif (null !== $data->collection) {
            $workspace = $data->collection->getWorkspace();
        }

        $isNew = null === $target;
        /** @var Asset $object */
        $object = $target ?? new Asset(
            $context[self::CONTEXT_CREATION_MICRO_TIME] ?? null,
            $data->sequence
        );

        if ($isNew) {
            if ($workspace instanceof Workspace && $data->key) {
                $asset = $this->em->getRepository(Asset::class)
                    ->findOneBy([
                        'key' => $data->key,
                        'workspace' => $workspace->getId(),
                    ]);

                if ($asset) {
                    // The POST operation only checks CREATE on the container:
                    // upserting an existing asset by its key requires EDIT on it.
                    if (!$this->isGranted(AbstractVoter::EDIT, $asset)) {
                        throw new AccessDeniedHttpException(sprintf('Not allowed to update asset with key "%s"', $data->key));
                    }

                    $isNew = false;
                    $object = $asset;
                }
            }
        }

        if ($data->trackingId) {
            $object->setTrackingId($data->trackingId);
        }

        if ($data->externalId) {
            $object->setExternalId($data->externalId);
        }

        if (null !== $data->getExtraMetadata()) {
            $object->setExtraMetadata($data->getExtraMetadata());
        }

        if ($isNew) {
            $object->setWorkspace($workspace);
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }

            if ($data->key) {
                $object->setKey($data->key);
            }

            if (null !== $data->collection) {
                if (null === $object->getReferenceCollection()) {
                    $object->setReferenceCollection($data->collection);
                }
                $object->addToCollection($data->collection, extraMetadata: $data->relationExtraMetadata);
            }

            if (!empty($data->attributes)) {
                $this->assignAttributes($object->getWorkspaceId(), $this->attributeInputProcessor, $object, $data->attributes, $context);
            }

            if ($data->relationship) {
                $this->handleRelationship($data->relationship, $object);
            }
        }

        if (null !== $data->name && null !== $object->getWorkspace()) {
            $this->assetNameFiller->fillName($object, $data->name);
        }

        if (null !== $file = $this->handleFile($data, $object)) {
            $this->renditionManager->resetAssetRenditions($object);
            $this->assetManager->assignNewAssetSourceFile($object, $file);
        }

        if (!empty($data->renditions)) {
            foreach ($data->renditions as $renditionInput) {
                $rendition = $this->renditionInputMapper->map($renditionInput, null, ['asset' => $object]
                );
                $this->em->persist($rendition);
            }
        }

        $this->renditionManager->deleteScheduledRenditions();

        if (isset($data->tags)) {
            $object->getTags()->clear();
            foreach ($data->tags as $tag) {
                $object->addTag($tag);
            }

            $object->setTagsEditedAt(new \DateTimeImmutable());
        }

        $object = $this->processOwnerId($object);

        if ($isNew && $data->isStory) {
            $this->assetManager->turnIntoStory($object);
            $this->addStoryItems($object, $data->storyAssets ?? [], $data->storyCover);
        } elseif (!empty($data->storyAssets) || null !== $data->storyCover) {
            throw new BadRequestHttpException('"storyAssets" and "storyCover" are only allowed when creating a story.');
        }

        $this->transformPrivacy($data, $object);

        return $object;
    }

    /**
     * @param string[] $assetIds
     */
    private function addStoryItems(Asset $story, array $assetIds, ?string $coverId): void
    {
        if (null !== $coverId && !in_array($coverId, $assetIds, true)) {
            throw new BadRequestHttpException('"storyCover" must be one of "storyAssets".');
        }
        if (null !== $coverId && null !== $story->getSource()) {
            throw new BadRequestHttpException('A story cannot have both a source file and a "storyCover".');
        }

        $storyCollection = $story->getStoryCollection();
        foreach (array_values(array_unique($assetIds)) as $assetId) {
            $asset = $this->em->find(Asset::class, $assetId);
            if (!$asset instanceof Asset) {
                throw new BadRequestHttpException(sprintf('Asset "%s" not found', $assetId));
            }
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                throw new AccessDeniedHttpException(sprintf('Cannot read asset "%s"', $assetId));
            }
            if ($asset->getWorkspaceId() !== $story->getWorkspaceId()) {
                throw new BadRequestHttpException(sprintf('Asset "%s" does not belong to the story workspace', $assetId));
            }

            $this->em->persist($asset->addToCollection($storyCollection));

            if ($assetId === $coverId) {
                $this->storyCoverManager->setCover($story, $asset);
            }
        }
    }

    private function handleFile(AssetInput $data, Asset $asset): ?File
    {
        $workspace = $asset->getWorkspace();
        if (null === $workspace) {
            // Will API will respond 422
            return null;
        }

        return $this->handleSource($data->sourceFile, $workspace)
            ?? $this->handleFromFile($data->sourceFileId, $workspace)
            ?? $this->handleUpload($data->multipart, $workspace);
    }

    private function handleRelationship(AssetRelationshipInput $input, Asset $asset): void
    {
        $rel = new AssetRelationship();
        $rel->setTarget($asset);
        $rel->setSource($this->getEntity(Asset::class, $input->source));

        if ($input->sourceFileId) {
            $rel->setSourceFile($this->getEntity(File::class, $input->sourceFileId));
        }
        if ($input->integration) {
            $rel->setIntegration($this->getEntity(WorkspaceIntegration::class, $input->integration));
        }

        $rel->setType($input->type);

        $this->em->persist($rel);
    }
}
