<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\MetadataTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MetadataTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetadataTag::class);
    }
}
