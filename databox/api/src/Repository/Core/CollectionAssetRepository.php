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

    /**
     * @param string[] $assetIds
     *
     * @return string[] the IDs of the given assets that are already in the collection
     */
    public function findAssetIdsInCollection(string $collectionId, array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        return array_column($this->createQueryBuilder('t')
            ->select('IDENTITY(t.asset) AS asset_id')
            ->andWhere('t.collection = :c')
            ->andWhere('t.asset IN (:a)')
            ->setParameter('c', $collectionId)
            ->setParameter('a', $assetIds)
            ->getQuery()
            ->getScalarResult(), 'asset_id');
    }

    public function findCollectionAsset(string $assetId, string $collectionId): ?CollectionAsset
    {
        return $this->findOneBy([
            'asset' => $assetId,
            'collection' => $collectionId,
        ]);
    }
}
