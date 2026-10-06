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
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        }

        $this->transformPrivacy($data, $object);

        return $object;
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
