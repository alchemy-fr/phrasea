<?php

declare(strict_types=1);

namespace App\Repository\Basket;

use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class BasketRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Basket::class);
    }

    public function getESQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('t')
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC');
    }

    public function removeFromBasket(string $basketId, array $itemIds): void
    {
        $this->getEntityManager()->createQueryBuilder('t')
            ->delete()
            ->from(BasketAsset::class, 't')
            ->andWhere('t.basket = :bid')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('bid', $basketId)
            ->setParameter('ids', $itemIds)
            ->getQuery()
            ->execute();
    }

    public function getMaxPosition(string $basketId): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(t.position) as m')
            ->from(BasketAsset::class, 't')
            ->andWhere('t.basket = :b')
            ->setParameter('b', $basketId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
}
