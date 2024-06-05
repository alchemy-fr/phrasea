<?php

declare(strict_types=1);

namespace App\Repository\Integration;

use App\Entity\Integration\IntegrationData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class IntegrationDataRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, IntegrationData::class);
    }
}
