<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AssetInput;
use App\Entity\Core\Asset;

class AssetInputDataTransformer extends AbstractInputDataTransformer
{
    /**
     * @param AssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Asset();
        $object->setTitle($data->title);
        $this->transformPrivacy($data, $object);

        if ($isNew) {
            if ($data->workspace) {
                $object->setWorkspace($data->workspace);
            } elseif (null !== $data->collection) {
                $object->setWorkspace($data->collection->getWorkspace());
            }
            $object->setOwnerId($this->getStrictUser()->getId());
        }

        if (isset($data->tags)) {
            $object->getTags()->clear();
            foreach ($data->tags as $tag) {
                $object->addTag($tag);
            }
        }

        if (null !== $data->collection) {
            if (null === $object->getReferenceCollection()) {
                $object->setReferenceCollection($data->collection);
            }
            $object->addToCollection($data->collection);
        }

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && AssetInput::class === ($context['input']['class'] ?? null);
    }
}
