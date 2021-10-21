<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Collection;

class CollectionInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    /**
     * @param CollectionInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Collection();
        $object->setTitle($data->title);
        $this->transformPrivacy($data, $object);

        if ($isNew) {
            if ($data->workspace) {
                $object->setWorkspace($data->workspace);
            } elseif (null !== $data->parent) {
                $object->setWorkspace($data->parent->getWorkspace());
            }
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }
        }

        if (null !== $data->parent) {
            $object->setParent($data->parent);
        }

        return $this->transformOwnerId($object, $to, $context);
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Collection) {
            return false;
        }

        return Collection::class === $to && CollectionInput::class === ($context['input']['class'] ?? null);
    }
}
