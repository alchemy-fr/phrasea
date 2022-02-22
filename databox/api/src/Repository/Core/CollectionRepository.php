<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Collection;
use Doctrine\ORM\EntityRepository;

class CollectionRepository extends EntityRepository
{
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findByKey(string $key, string $workspaceId): ?Collection
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.key = :key')
            ->andWhere('t.workspace = :ws')
            ->setParameter('key', $key)
            ->setParameter('ws', $workspaceId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
