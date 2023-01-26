<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use App\Entity\Core\AssetFileVersion;

class AssetFileVersionCollectionDataProvider extends AbstractAssetFilteredCollectionDataProvider
{
    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $asset = $this->getAsset($context);

        return $this->em->getRepository(AssetFileVersion::class)
            ->createQueryBuilder('t')
            ->andWhere('t.asset = :a')
            ->setParameter('a', $asset->getId())
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AssetFileVersion::class === $resourceClass;
    }
}
