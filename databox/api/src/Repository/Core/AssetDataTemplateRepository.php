<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Template\AssetDataTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


class AssetDataTemplateRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    )
    {
        parent::__construct($registry, AssetDataTemplate::class);
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
