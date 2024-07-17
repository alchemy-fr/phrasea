<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttributeEntity;
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
}
