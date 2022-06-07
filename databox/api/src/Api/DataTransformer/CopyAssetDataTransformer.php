<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Input\CopyAssetInput;
use App\Entity\Core\Asset;

class CopyAssetDataTransformer implements DataTransformerInterface
{
    /**
     * @param CopyAssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $object = new Asset();
        $object->copyAction = $data;

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && CopyAssetInput::class === ($context['input']['class'] ?? null);
    }
}
