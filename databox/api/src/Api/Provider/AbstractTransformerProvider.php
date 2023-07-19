<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\ItemProviderAwareTrait;

abstract class AbstractTransformerProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;
    use CollectionProviderAwareTrait;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            $result = $this->itemProvider->provide($operation, $uriVariables, $context);

            if (!is_array($result)) {
                $result = iterator_to_array($result);
            }

            return array_map(fn (object $item): object => $this->transform($item, $context), $result);
        }

        return $this->transform($this->collectionProvider->provide($operation, $uriVariables, $context), $context);
    }

    abstract protected function transform(object $data, array $context): object;
}
