<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
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
}
