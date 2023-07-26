<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetRelationshipInput;
use App\Asset\AssetManager;
use App\Asset\OriginalRenditionManager;
use App\Consumer\Handler\File\CopyFileToAssetHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRelationship;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetInputProcessor extends AbstractFileInputProcessor
{
    use WithOwnerIdProcessorTrait;
    use AttributeInputTrait;

    final public const CONTEXT_CREATION_MICRO_TIME = 'micro_time';

    public function __construct(
        private readonly OriginalRenditionManager $originalRenditionManager,
        private readonly AttributeInputProcessor $attributeInputProcessor,
        private readonly AssetManager $assetManager,
    ) {
    }

    /**
     * @param AssetInput $data
     */
    protected function transform(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        } elseif (null !== $data->collection) {
            $workspace = $data->collection->getWorkspace();
        }

        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Asset $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Asset(
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
                    $isNew = false;
                    $object = $asset;
                }
            }
        }

        if ($data->title) {
            $object->setTitle($data->title);
        }
        if ($data->pendingUploadToken) {
            $object->setPendingUploadToken($data->pendingUploadToken);
        }

        $this->transformPrivacy($data, $object);

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
                $object->addToCollection($data->collection);
            }

            if (!empty($data->attributes)) {
                $this->assignAttributes($this->attributeInputProcessor, $object, $data->attributes, Attribute::class, $context);
            }

            if ($data->relationship) {
                $this->handleRelationship($data->relationship, $object);
            }
        }

        if (null !== $file = $this->handleFile($data, $object)) {
            if (null !== $object->getPendingUploadToken()) {
                throw new BadRequestHttpException(sprintf('Asset "%s" has pending upload, cannot provide file', $object->getId()));
            }

            $this->renditionManager->resetAssetRenditions($object);

            $this->assetManager->assignNewAssetSourceFile($object, $file);
        }

        if (!empty($data->renditions)) {
            foreach ($data->renditions as $renditionInput) {
                $definition = $this->renditionManager->getRenditionDefinitionByName(
                    $workspace,
                    $renditionInput->definition
                );
                $rendition = $this->renditionManager->getOrCreateRendition($object, $definition);
                $file = $this->handleSource($renditionInput->source, $workspace);
                $rendition->setFile($file);

                $this->em->persist($rendition);
            }
        }

        $this->renditionManager->deleteScheduledRenditions();

        if (isset($data->tags)) {
            $object->getTags()->clear();
            foreach ($data->tags as $tag) {
                $object->addTag($tag);
            }
        }

        return $this->processOwnerId($object);
    }

    private function handleFile(AssetInput $data, Asset $asset): ?File
    {
        if (null === $asset->getWorkspace()) {
            // Will API will respond 422
            return null;
        }

        if (null !== $file = $this->handleSource($data->sourceFile, $asset->getWorkspace())) {
            return $file;
        } elseif (null !== $file = $this->handleFromFile($data->sourceFileId)) {
            $this->postFlushStackListener->addEvent(CopyFileToAssetHandler::createEvent($asset->getId(), $file->getId()));

            return $file;
        } elseif (null !== $file = $this->handleUpload($asset->getWorkspace())) {
            return $file;
        }

        return null;
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
