<?php

namespace App\Repository\Discussion;

use App\Entity\Discussion\Thread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ThreadRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Thread::class);
    }

    public function getThreadOfKey(string $key): ?Thread
    {
        return $this
            ->createQueryBuilder('t')
            ->andWhere('t.key = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
