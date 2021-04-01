<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\TagOutput;
use App\Entity\Core\Tag;

class TagOutputDataTransformer implements DataTransformerInterface
{
    /**
     * @param Tag $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new TagOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return TagOutput::class === $to && $data instanceof Tag;
    }
}
