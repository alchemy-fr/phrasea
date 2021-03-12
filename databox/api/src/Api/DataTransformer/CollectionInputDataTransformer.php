<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Collection;

class CollectionInputDataTransformer extends AbstractInputDataTransformer
{
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
            $object->setWorkspace($data->workspace);
        }
        if ($isNew) {
            $object->setOwnerId($this->getStrictUser()->getId());
        }

        if (null !== $data->parent) {
            $object->set($data->parent);
        }

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Collection) {
            return false;
        }

        return Collection::class === $to && CollectionInput::class === ($context['input']['class'] ?? null);
    }
}
