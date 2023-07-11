<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Entity\Core\Asset;

class BatchAssetAttributeInputProcessor implements ProcessorInterface
{
    /**
     * @param AssetAttributeBatchUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
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

        return Asset::class === $to && AssetAttributeBatchUpdateInput::class === ($context['input']['class'] ?? null);
    }
}
