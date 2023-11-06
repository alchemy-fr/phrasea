<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\MoveAssetInput;

class MoveAssetInputTransformer extends AbstractFileInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return $data instanceof MoveAssetInput;
    }

    /**
     * @param MoveAssetInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        return $data;
    }
}
