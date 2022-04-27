<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Attribute\BatchAssetAttributeInput;
use App\Entity\Core\Asset;

class BatchAssetAttributeInputDataTransformer implements DataTransformerInterface
{
    /**
     * @param BatchAssetAttributeInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        /** @var Asset $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Asset();

        $object->attributeActions = $data;

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && BatchAssetAttributeInput::class === ($context['input']['class'] ?? null);
    }
}
