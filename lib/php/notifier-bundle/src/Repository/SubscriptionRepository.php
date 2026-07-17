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
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT sub.userId AS userId')
            ->innerJoin('s.subscriber', 'sub')
            ->andWhere('s.event = :event')
            ->setParameter('event', $selector->event)
        ;

        // A null object means the subscription is not scoped to an object:
        // it must be matched with IS NULL, as "= NULL" is never true.
        if (null === $selector->objectType) {
            $qb->andWhere('s.objectType IS NULL');
        } else {
            $qb->andWhere('s.objectType = :type')->setParameter('type', $selector->objectType);
        }

        if (null === $selector->objectId) {
            $qb->andWhere('s.objectId IS NULL');
        } else {
            $qb->andWhere('s.objectId = :id')->setParameter('id', $selector->objectId);
        }

        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $r): string => (string) $r['userId'], $rows);
    }
}
