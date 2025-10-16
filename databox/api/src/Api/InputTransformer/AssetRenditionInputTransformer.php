<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AssetRenditionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AssetRenditionInputTransformer extends AbstractFileInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return AssetRendition::class === $resourceClass && $data instanceof AssetRenditionInput;
    }

    /**
     * @param AssetRenditionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
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
}
