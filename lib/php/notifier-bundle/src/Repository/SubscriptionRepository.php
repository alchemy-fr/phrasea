<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findOneForObject(Subscriber $subscriber, string $objectType, string $objectId): ?Subscription
    {
        return $this->findOneBy([
            'subscriber' => $subscriber,
            'objectType' => $objectType,
            'objectId' => $objectId,
        ]);
    }

    /**
     * @return array<int, string> The userIds of all subscribers following the object
     */
    public function findSubscriberUserIds(string $objectType, string $objectId): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT sub.userId AS userId')
            ->innerJoin('s.subscriber', 'sub')
            ->andWhere('s.objectType = :type')
            ->andWhere('s.objectId = :id')
            ->setParameter('type', $objectType)
            ->setParameter('id', $objectId)
            ->getQuery()
            ->getScalarResult()
        ;

        return array_map(static fn (array $r): string => (string) $r['userId'], $rows);
    }
}
