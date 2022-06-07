<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Entity\Core\Asset;

class MoveAssetDataTransformer implements DataTransformerInterface
{
    /**
     * @param MoveAssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $object = new Asset();
        $object->moveAction = $data;

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && MoveAssetInput::class === ($context['input']['class'] ?? null);
    }
}
