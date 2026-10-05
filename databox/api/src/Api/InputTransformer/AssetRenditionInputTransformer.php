<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Input\AssetRenditionInput;
use App\Consumer\Handler\Rendition\BuildDynamicRendition;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AssetRenditionInputTransformer extends AbstractFileInputTransformer
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetRenditionRepository $assetRenditionRepository,
    ) {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return AssetRendition::class === $resourceClass && $data instanceof AssetRenditionInput;
    }

    /**
     * @param AssetRenditionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        if (null !== $data->buildDefinition) {
            return $this->transformDynamicRendition($data, $context);
        }

        /** @var AssetRendition $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $isNew = null === $object;

        if ($isNew) {
            $object = new AssetRendition();
            $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);
            if ($data->definitionId) {
                $definition = $this->getEntity(RenditionDefinition::class, $data->definitionId);
            } elseif ($data->name) {
                $definition = $this->renditionManager
                    ->getRenditionDefinitionByName($asset->getWorkspaceId(), $data->name);
            } else {
                throw new BadRequestHttpException('Missing "definitionId" or "name"');
            }

            $object->setDefinition($definition);
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

    private function transformDynamicRendition(AssetRenditionInput $data, array $context): AssetRendition
    {
        if (null !== ($context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null)) {
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
        } elseif (null === $asset->getSource()) {
            throw new BadRequestHttpException(sprintf('Asset "%s" has no source file', $asset->getId()));
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

        $this->postFlushStackListener->addBusMessage(new BuildDynamicRendition($rendition->getId()));

        return $rendition;
    }
}
