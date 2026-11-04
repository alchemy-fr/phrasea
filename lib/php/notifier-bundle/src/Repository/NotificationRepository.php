<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function createForSubscriberQueryBuilder(Subscriber $subscriber, ?bool $unreadOnly = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->orderBy('n.createdAt', 'DESC')
        ;

        if (true === $unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        return $qb;
    }

    public function countUnread(Subscriber $subscriber): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.subscriber = :subscriber')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('subscriber', $subscriber)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function markAllAsRead(Subscriber $subscriber): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->andWhere('n.subscriber = :subscriber')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('subscriber', $subscriber)
            ->getQuery()
            ->execute()
        ;
    }
}
