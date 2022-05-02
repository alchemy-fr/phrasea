<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Entity\Core\Attribute;

class BatchAttributeUpdateDataTransformer implements DataTransformerInterface
{
    /**
     * @param AttributeBatchUpdateInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $object = new Attribute();
        $object->batchUpdate = $data;

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Attribute) {
            return false;
        }

        return Attribute::class === $to && AttributeBatchUpdateInput::class === ($context['input']['class'] ?? null);
    }
}
