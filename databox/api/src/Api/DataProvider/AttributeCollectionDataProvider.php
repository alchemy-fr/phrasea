<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use App\Entity\Core\Attribute;

class AttributeCollectionDataProvider extends AbstractAssetFilteredCollectionDataProvider
{
    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $asset = $this->getAsset($context);

        $criteria = [
            'asset' => $asset->getId(),
        ];

        return $this->em->getRepository(Attribute::class)->findBy($criteria, [
            'position' => 'ASC',
        ]);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Attribute::class === $resourceClass;
    }
}
