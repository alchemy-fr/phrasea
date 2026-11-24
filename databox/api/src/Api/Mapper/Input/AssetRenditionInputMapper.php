<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Input\AssetRenditionInput;
use App\Consumer\Handler\File\ImportFile;
use App\Consumer\Handler\Rendition\BuildDynamicRendition;
use App\Consumer\MessageDeduplication;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\FileProcessingGuard;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: AssetRenditionInput::class)]
class AssetRenditionInputMapper extends AbstractFileInputMapper implements InputMapperInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetRenditionRepository $assetRenditionRepository,
        private readonly FileProcessingGuard $processingGuard,
    ) {
    }

    /**
     * @param AssetRenditionInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        if (null !== $data->buildDefinition) {
            return $this->transformDynamicRendition($data, $target, $context);
        }

        /** @var AssetRendition $object */
        $object = $target;
        $isNew = null === $object;

        if ($isNew) {
            $object = new AssetRendition();
            $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);
            $object->setDefinition($this->getDefinition($data, $asset));
            $object->setAsset($asset);
            $asset->getRenditions()->removeElement($object);
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $object);

        $workspace = $object->getAsset()->getWorkspace();
        $file = $this->handleSource($data->sourceFile, $workspace)
            ?? $this->handleFromFile($data->sourceFileId, $workspace)
            ?? $this->handleUpload($data->multipart, $workspace);
        if (null !== $file) {
            $this->em->persist($file);

            // A rendition has no ingest workflow to import its file.
            if ($file->isImportPending()) {
                $this->postFlushStackListener->addBusMessage(new ImportFile($file->getId()));
            }
        }

        return $this->renditionManager->createOrReplaceRenditionFile(
            $object->getAsset(),
            $object->getDefinition(),
            $file,
            $file ? null : $object->getBuildHash(),
            $file ? null : $object->getModuleHashes(),
            $data->substituted ?? $object->isSubstituted(),
            $object->isLocked(),
            $data->force ?? false,
            $file ? false : $object->getProjection()
        );
    }

    /**
     * Only a definition of the asset workspace: one of another workspace is answered as an unknown one.
     */
    private function getDefinition(AssetRenditionInput $data, Asset $asset): RenditionDefinition
    {
        if ($data->definitionId) {
            /** @var RenditionDefinition $definition */
            $definition = $this->getEntity(RenditionDefinition::class, $data->definitionId);
            if ($definition->getWorkspaceId() !== $asset->getWorkspaceId()) {
                throw new BadRequestHttpException(sprintf('Rendition definition "%s" not found', $data->definitionId));
            }

            return $definition;
        }

        if ($data->name) {
            try {
                return $this->renditionManager->getRenditionDefinitionByName($asset->getWorkspaceId(), $data->name);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }

        throw new BadRequestHttpException('Missing "definitionId" or "name"');
    }

    private function transformDynamicRendition(AssetRenditionInput $data, ?object $target, array $context): AssetRendition
    {
        if (null !== $target) {
            throw new BadRequestHttpException('"buildDefinition" is only supported on creation');
        }
        if (empty($data->name)) {
            throw new BadRequestHttpException('Missing "name" for the dynamic rendition');
        }

        /** @var Asset $asset */
        $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);

        if (null !== $data->sourceRenditionId) {
            /** @var AssetRendition $sourceRendition */
            $sourceRendition = $this->getEntity(AssetRendition::class, $data->sourceRenditionId);
            if ($sourceRendition->getAsset()->getId() !== $asset->getId()) {
                throw new BadRequestHttpException(sprintf('Rendition "%s" does not belong to asset "%s"', $data->sourceRenditionId, $asset->getId()));
            }
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $sourceRendition);
            if (!$sourceRendition->isReady()) {
                throw new BadRequestHttpException(sprintf('Rendition "%s" has no file yet', $data->sourceRenditionId));
            }
            $source = $sourceRendition->getFile();
        } else {
            $source = $asset->getSource();
            if (null === $source) {
                throw new BadRequestHttpException(sprintf('Asset "%s" has no source file', $asset->getId()));
            }
        }

        $rendition = $this->assetRenditionRepository->findDynamicRenditionByName($asset->getId(), $data->name);
        if (null === $rendition) {
            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setName($data->name);
            $asset->getRenditions()->removeElement($rendition);
        } else {
            throw new BadRequestHttpException(sprintf('Dynamic rendition "%s" already exists for asset "%s"', $data->name, $asset->getId()));
        }

        $rendition->setFile(null);
        $rendition->setBuildHash(null);
        $rendition->setModuleHashes(null);
        $rendition->setProjection(null);
        $rendition->setBuildDefinition($data->buildDefinition);
        $rendition->setBuildOptions(array_filter([
            AssetRendition::OPTION_WRITE_METADATA => $data->writeMetadata ?? false,
            AssetRendition::OPTION_SOURCE_RENDITION_ID => $data->sourceRenditionId,
        ], fn ($value): bool => null !== $value && false !== $value) ?: null);

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $rendition);

        if (null !== $source && null !== $refusal = $this->processingGuard->getRefusal($source)) {
            throw new BadRequestHttpException(sprintf('Rendition cannot be built: %s', $refusal));
        }

        $this->postFlushStackListener->addBusMessage(MessageDeduplication::coalesce(new BuildDynamicRendition($rendition->getId()), 900));

        return $rendition;
    }
}
