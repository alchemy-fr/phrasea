<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAccess;
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

    /**
     * @param string[] $allowedWorkspaces
     */
    public function getRootCollections(array $allowedWorkspaces, string $userId, array $groups): array
    {
        $expr = $this->_em->getExpressionBuilder();

        $sub = $this->_em->createQueryBuilder()
            ->select('1')
            ->from(CollectionAccess::class, 'a')
            ->andWhere('a.workspace IN (:ws)')
            ->andWhere('IDENTITY(a.collection) <> IDENTITY(ca.collection)')
            ->andWhere('CONTAINS(a.path, ca.path) = TRUE')
            ->andWhere('a.userId IN (:users) OR a.privacy > 0')
        ;

        return $this
            ->createQueryBuilder('t')
            ->where($expr->in(
                't.id',
                $this->_em->createQueryBuilder()
                    ->select('DISTINCT IDENTITY(ca.collection)')
                    ->from(CollectionAccess::class, 'ca')
                    ->andWhere('ca.workspace IN (:ws)')
                    ->andWhere('ca.userId IN (:users) OR ca.privacy > 0')
                    ->andWhere($expr->not($expr->exists($sub->getDQL())))
                    ->getDQL()
            ))
            ->addOrderBy('t.name', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->setParameter('users', array_merge([$userId], $groups))
            ->setParameter('ws', $allowedWorkspaces)
            ->getQuery()
            ->getResult();
    }
}
