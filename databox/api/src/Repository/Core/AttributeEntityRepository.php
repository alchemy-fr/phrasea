<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AttributeEntityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AttributeEntity::class);
    }

    public function getESQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('t')
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC')
        ;
    }

    /**
     * The approved values of a list, by position then value.
     *
     * @param string[] $ids   restricts to these values when not empty
     * @param ?string  $query contains match on the value, case-insensitive
     *
     * @return AttributeEntity[]
     */
    public function findApprovedOfList(EntityList $list, ?string $query, array $ids, int $limit, int $offset): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->andWhere('t.list = :list')
            ->andWhere('t.status = :status')
            ->setParameter('list', $list)
            ->setParameter('status', AttributeEntity::STATUS_APPROVED)
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('t.value', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ([] !== $ids) {
            $queryBuilder
                ->andWhere('t.id IN (:ids)')
                ->setParameter('ids', $ids);
        }
        if (null !== $query) {
            $queryBuilder
                ->andWhere("LOWER(t.value) LIKE :query ESCAPE '!'")
                ->setParameter('query', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)).'%');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
