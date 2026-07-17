<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Entity\Subscription;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
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

    public function findOneForObject(Subscriber $subscriber, NotifySelectorDto $selector): ?Subscription
    {
        return $this->findOneBy([
            'subscriber' => $subscriber,
            'event' => $selector->event,
            'objectType' => $selector->objectType,
            'objectId' => $selector->objectId,
        ]);
    }

    /**
     * @return array<int, string> The userIds of all subscribers following the topic + object
     */
    public function findSubscriberUserIds(NotifySelectorDto $selector): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT sub.userId AS userId')
            ->innerJoin('s.subscriber', 'sub')
            ->andWhere('s.event = :event')
            ->andWhere('s.objectType = :type')
            ->andWhere('s.objectId = :id')
            ->setParameter('event', $selector->event)
            ->setParameter('type', $selector->objectType)
            ->setParameter('id', $selector->objectId)
            ->getQuery()
            ->getScalarResult()
        ;

        return array_map(static fn (array $r): string => (string) $r['userId'], $rows);
    }
}
