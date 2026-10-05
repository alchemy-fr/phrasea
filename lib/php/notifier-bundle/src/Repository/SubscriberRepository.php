<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscriber>
 */
class SubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscriber::class);
    }

    public function findOneByUserId(string $userId): ?Subscriber
    {
        return $this->findOneBy(['userId' => $userId]);
    }

    /**
     * Memory-efficient iterator over every subscriber userId (for broadcasts).
     *
     * @return iterable<int, string>
     */
    public function iterateUserIds(): iterable
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.userId')
            ->getQuery()
            ->toIterable()
        ;

        foreach ($result as $row) {
            yield (string) $row['userId'];
        }
    }
}
