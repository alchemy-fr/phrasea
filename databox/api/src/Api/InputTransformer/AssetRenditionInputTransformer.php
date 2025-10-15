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
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var AssetRendition $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;

        if ($isNew) {
            $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);
            if ($data->definitionId) {
                $definition = $this->getEntity(RenditionDefinition::class, $data->definitionId);
            } elseif ($data->name) {
                $definition = $this->renditionManager
                    ->getRenditionDefinitionByName($asset->getWorkspaceId(), $data->name);
            } else {
                throw new BadRequestHttpException('Missing "definitionId" or "name"');
            }

            $object = $this->renditionManager->getOrCreateRendition($asset, $definition);
            if ($object->isLocked()) {
                throw new BadRequestHttpException('Cannot update locked rendition');
            }

            if ($object->isSubstituted() && !$data->force) {
                throw new BadRequestHttpException('Cannot update rendition that has been substituted without the "force" parameter');
            }
        }

        if (!$object->getDefinition()->isSubstitutable()) {
            throw new BadRequestHttpException(sprintf('Cannot substitute rendition "%s"', $object->getDefinition()->getName()));
        }

        $object->setSubstituted($data->substituted);

        $workspace = $object->getAsset()->getWorkspace();
        $file = $this->handleSource($data->sourceFile, $workspace)
            ?? $this->handleFromFile($data->sourceFileId, $workspace)
            ?? $this->handleUpload($data->multipart, $workspace);

        if (null !== $file) {
            $object->setFile($file);
        }

        return $object;
    }
}
