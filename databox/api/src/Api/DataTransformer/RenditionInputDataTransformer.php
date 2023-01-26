<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\RenditionInput;
use App\Consumer\Handler\File\CopyFileToRenditionHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RenditionInputDataTransformer extends AbstractFileInputDataTransformer
{
    /**
     * @param RenditionInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $this->validator->validate($data);

        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var AssetRendition $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? null;

        if ($isNew) {
            $asset = $this->getEntity(Asset::class, $data->assetId);
            if ($data->definitionId) {
                $definition = $this->getEntity(RenditionDefinition::class, $data->definitionId);
            } elseif ($data->name) {
                $definition = $this->renditionManager->getRenditionDefinitionByName($object->getAsset()->getWorkspace(), $data->name);
            } else {
                throw new BadRequestHttpException('Missing "definitionId" or "name"');
            }

            $object = $this->renditionManager->getOrCreateRendition($asset, $definition);
        }

        $workspace = $object->getAsset()->getWorkspace();

        if (null !== $file = $this->handleSource($data->source, $workspace)) {
            $object->setFile($file);
        } elseif (null !== $file = $this->handleFromFile($data->sourceFileId)) {
            $this->postFlushStackListener->addEvent(CopyFileToRenditionHandler::createEvent($object->getId(), $file->getId()));
            $object->setFile($file);
        } elseif (null !== $file = $this->handleUpload($workspace)) {
            $object->setFile($file);
        }

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof AssetRendition) {
            return false;
        }

        return AssetRendition::class === $to && RenditionInput::class === ($context['input']['class'] ?? null);
    }
}
