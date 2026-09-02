<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\Broadcast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Broadcast>
 */
class BroadcastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Broadcast::class);
    }
}
