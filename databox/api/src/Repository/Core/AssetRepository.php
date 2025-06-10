<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findByKeys(array $keys, string $workspaceId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.workspace = :ws')
            ->setParameter('ws', $workspaceId)
            ->andWhere('t.key IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getResult();
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

    public function getESQueryBuilder(string $alias = 't'): QueryBuilder
    {
        return $this
            ->createQueryBuilder($alias)
            ->addOrderBy($alias.'.createdAt', 'DESC')
            ->addOrderBy($alias.'.id', 'ASC')
        ;
    }
}
