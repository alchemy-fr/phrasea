<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Collection;
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

    /**
     * The story items a contact sheet is made of: not deleted, accepted, in display order.
     *
     * Queried in SQL, not in Elasticsearch: the sheet is built right after the story
     * changes, before the index reflects it. It is a system build, shown to every
     * reader of the story like a cover: no user ACL applies, nothing is paginated.
     *
     * @return string[] the asset IDs
     */
    public function findStoryContactSheetItemIds(Collection $storyCollection, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        return array_column($this->createQueryBuilder('t')
            ->select('IDENTITY(t.asset) AS asset_id')
            ->innerJoin('t.asset', 'a')
            ->andWhere('t.collection = :c')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.status = :accepted')
            ->setParameter('c', $storyCollection->getId())
            ->setParameter('accepted', AssetStatusEnum::Accepted->value)
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult(), 'asset_id');
    }

    /**
     * @return string[] the IDs of the story assets whose story contains one of the given assets
     */
    public function findStoryAssetIdsContaining(array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        return array_values(array_unique(array_column($this->createQueryBuilder('t')
            ->select('IDENTITY(c.storyAsset) AS story_id')
            ->innerJoin('t.collection', 'c')
            ->andWhere('t.asset IN (:a)')
            ->andWhere('c.storyAsset IS NOT NULL')
            ->setParameter('a', $assetIds)
            ->getQuery()
            ->getScalarResult(), 'story_id')));
    }

    public function findCollectionAsset(string $assetId, string $collectionId): ?CollectionAsset
    {
        return $this->findOneBy([
            'asset' => $assetId,
            'collection' => $collectionId,
        ]);
    }
}
