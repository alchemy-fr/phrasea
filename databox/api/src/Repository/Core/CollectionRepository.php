<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Collection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class CollectionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Collection::class);
    }

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

    public function getESQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('t')
            ->andWhere('t.storyAsset IS NULL')
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC')
        ;
    }
}
