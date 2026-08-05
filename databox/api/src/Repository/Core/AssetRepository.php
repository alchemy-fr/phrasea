<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AssetRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Asset::class);
    }

    /**
     * @return Asset[]
     */
    public function getCollectionAssets(string $collectionId): array
    {
        return $this
            ->createQueryBuilder('a')
            ->innerJoin('a.collections', 'ac')
            ->andWhere('ac.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Asset[]
     */
    public function getCollectionAssetIdsIterator(string $collectionId): iterable
    {
        return $this
            ->createQueryBuilder('a')
            ->distinct()
            ->select('a.id')
            ->innerJoin('a.collections', 'ac')
            ->andWhere('ac.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return Asset[]
     */
    public function findByKeys(array $keys, string $workspaceId): iterable
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.workspace = :ws')
            ->setParameter('ws', $workspaceId)
            ->andWhere('t.key IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return Asset[]
     */
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $fileIds
     *
     * @return Asset[]
     */
    public function findBySourceFileIds(array $fileIds): array
    {
        if (empty($fileIds)) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->andWhere('t.source IN (:fileIds)')
            ->setParameter('fileIds', $fileIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Stream the ids of every asset of a workspace without hydrating entities.
     *
     * @return iterable<string>
     */
    public function iterateIdsByWorkspace(string $workspaceId): iterable
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.id AS assetId')
            ->andWhere('a.workspace = :ws')
            ->setParameter('ws', $workspaceId)
            ->getQuery()
            ->toIterable([], AbstractQuery::HYDRATE_SCALAR);

        foreach ($result as $row) {
            yield $row['assetId'];
        }
    }

    public function getESQueryBuilder(string $alias = 't'): QueryBuilder
    {
        return $this
            ->createQueryBuilder($alias)
            ->addOrderBy($alias.'.createdAt', 'DESC')
            ->addOrderBy($alias.'.id', 'ASC')
        ;
    }

    public function createElasticaToModelQueryBuilder(string $alias = 't'): QueryBuilder
    {
        return $this
            ->createQueryBuilder($alias)
            ->select($alias.', f, w')
            ->leftJoin($alias.'.source', 'f')
            ->leftJoin($alias.'.workspace', 'w')
        ;
    }
}
