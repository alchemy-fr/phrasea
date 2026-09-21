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

    /**
     * @return int|null the highest position in the collection, or null when it is empty
     */
    public function getMaxPosition(string $collectionId): ?int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->andWhere('t.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? null : (int) $max;
    }

    /**
     * @return array<int, array{id: string, position: int}> the collection relations, in display order
     */
    public function findOrderedRelations(string $collectionId): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.id AS id', 't.position AS position')
            ->andWhere('t.collection = :c')
            ->setParameter('c', $collectionId)
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(fn (array $row): array => [
            'id' => $row['id'],
            'position' => (int) $row['position'],
        ], $rows);
    }

    public function findCollectionAsset(string $assetId, string $collectionId): ?CollectionAsset
    {
        return $this->findOneBy([
            'asset' => $assetId,
            'collection' => $collectionId,
        ]);
    }
}
