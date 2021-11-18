<?php

declare(strict_types=1);

namespace App\Repository\Core;

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
}
