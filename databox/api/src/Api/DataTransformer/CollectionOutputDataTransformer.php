<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\CollectionOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;

class CollectionOutputDataTransformer implements DataTransformerInterface
{
    /**
     * @param Collection $object
     *
     * @return CollectionOutput
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new CollectionOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());

        $output->setChildren(array_map(function (Collection $child) use ($context): CollectionOutput {
            return $this->transform($child, CollectionOutput::class, $context);
        }, $object->getChildren()->getValues()));

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return CollectionOutput::class === $to && $data instanceof Collection;
    }
}
