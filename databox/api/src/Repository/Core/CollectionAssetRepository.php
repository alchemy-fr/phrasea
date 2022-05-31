<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityRepository;

class CollectionAssetRepository extends EntityRepository
{
    public function deleteCollectionAsset(string $assetId, string $collectionId): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.asset = :a')
            ->andWhere('t.collection = :c')
            ->setParameter('a', $assetId)
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->execute();
    }

    public function findCollectionAsset(string $assetId, string $collectionId): ?CollectionAsset
    {
        return $this->findOneBy([
            'asset' => $assetId,
            'collection' => $collectionId,
        ]);
    }
}
