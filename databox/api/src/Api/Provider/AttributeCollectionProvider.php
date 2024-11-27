<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Attribute;

class AttributeCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $asset = $this->getAsset($context);

        return $this->em->getRepository(Attribute::class)
            ->getAssetAttributes($asset->getId())
        ;
    }
}
