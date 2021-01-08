<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;

class AssetOutputDataTransformer implements DataTransformerInterface
{
    /**
     * @param Asset $object
     *
     * @return AssetOutput
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setTags([]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
