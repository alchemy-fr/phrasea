<?php

declare(strict_types=1);

namespace App\OperationTask;

use App\Entity\Admin\OperationTask;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Race-safe progress accounting for tasks that fan out their work over many
 * concurrent messages. Every mutation is an atomic SQL UPDATE so parallel
 * workers never lose an increment or complete the task twice.
 */
final readonly class OperationTaskProgress
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Reset the progress counter and leave the total unknown until every
     * sub-message has been dispatched (prevents premature completion).
     */
    public function init(string $taskId): void
    {
        $this->em->createQuery(
            sprintf('UPDATE %s t SET t.progress = 0, t.itemTotal = NULL WHERE t.id = :id', OperationTask::class)
        )
            ->setParameter('id', $taskId)
            ->execute();
    }

    public function setItemTotal(string $taskId, int $total): void
    {
        $this->em->createQuery(
            sprintf('UPDATE %s t SET t.itemTotal = :total WHERE t.id = :id', OperationTask::class)
        )
            ->setParameter('total', $total)
            ->setParameter('id', $taskId)
            ->execute();
    }

    public function increment(string $taskId): void
    {
        $this->em->createQuery(
            sprintf('UPDATE %s t SET t.progress = t.progress + 1 WHERE t.id = :id', OperationTask::class)
        )
            ->setParameter('id', $taskId)
            ->execute();
    }

    /**
     * Atomically complete the task iff every item has been processed. The
     * status guard makes this exactly-once even when several workers reach
     * the last item at the same time.
     */
    public function finalizeIfComplete(string $taskId): void
    {
        $this->em->createQuery(
            sprintf(
                'UPDATE %s t SET t.status = :completed, t.endedAt = :now
                 WHERE t.id = :id
                   AND t.itemTotal IS NOT NULL
                   AND t.progress >= t.itemTotal
                   AND t.status = :inProgress',
                OperationTask::class
            )
        )
            ->setParameter('completed', OperationTask::STATUS_COMPLETED)
            ->setParameter('inProgress', OperationTask::STATUS_IN_PROGRESS)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $taskId)
            ->execute();
    }
}
