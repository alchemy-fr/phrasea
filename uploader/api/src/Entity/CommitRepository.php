<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;

class CommitRepository extends EntityRepository
{
    /**
     * @return Commit[]
     */
    public function getAcknowledgedBefore(\DateTimeImmutable $date): array
    {
        return $this
            ->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.acknowledged = true')
            ->andWhere('c.acknowledgedAt < :date')
            ->setParameter('date', $date->format('Y-m-d H:i:s'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Marks the notification of the target as dispatched, unless it already is or the commit is acknowledged.
     *
     * @return bool whether it was marked, that is whether the notification is to dispatch now
     */
    public function claimNotifyDispatch(string $id): bool
    {
        return 1 === (int) $this
            ->createQueryBuilder('c')
            ->update(Commit::class, 'c')
            ->set('c.notifyDispatchedAt', ':now')
            ->andWhere('c.id = :id')
            ->andWhere('c.notifyDispatchedAt IS NULL')
            ->andWhere('c.acknowledged = false')
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function setNotifyDispatchedAt(string $id, ?\DateTimeImmutable $date): void
    {
        $this
            ->createQueryBuilder('c')
            ->update(Commit::class, 'c')
            ->set('c.notifyDispatchedAt', ':date')
            ->andWhere('c.id = :id')
            ->setParameter('date', $date, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
