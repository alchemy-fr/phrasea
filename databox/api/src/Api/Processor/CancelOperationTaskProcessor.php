<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Admin\OperationTask;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Cancels a pending or running task. The cancellation is cooperative: a running
 * task stops at its next progress update, a pending one is skipped by the worker.
 */
final readonly class CancelOperationTaskProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param OperationTask $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OperationTask
    {
        if (!$data->isCancellable()) {
            throw new ConflictHttpException('Only a pending or running task can be cancelled');
        }

        // Atomic, so that a task completing meanwhile is not turned into a cancelled one
        $updated = $this->em->createQuery(
            sprintf(
                'UPDATE %s t SET t.status = :cancelled, t.endedAt = :now WHERE t.id = :id AND t.status IN (:cancellable)',
                OperationTask::class
            )
        )
            ->setParameter('cancelled', OperationTask::STATUS_CANCELLED)
            ->setParameter('cancellable', [OperationTask::STATUS_PENDING, OperationTask::STATUS_IN_PROGRESS])
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $data->getId())
            ->execute();

        if (0 === $updated) {
            throw new ConflictHttpException('Only a pending or running task can be cancelled');
        }

        $this->em->refresh($data);

        return $data;
    }
}
