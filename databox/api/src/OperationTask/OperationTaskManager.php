<?php

namespace App\OperationTask;

use Alchemy\AuthBundle\Security\JwtInterface;
use App\Consumer\Handler\RunOperationTask;
use App\Entity\Admin\OperationTask;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class OperationTaskManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private OperationTaskRegistry $taskRegistry,
        private LoggerInterface $logger,
    ) {
    }

    public function createTask(
        JwtInterface $user,
        string $taskName,
        array $payload = [],
    ): OperationTask {
        $taskHandler = $this->taskRegistry->getTask($taskName);

        $taskHandler->validate($payload);

        $task = new OperationTask();
        $task->setOwnerId($user->getUserIdentifier());
        $task->setPayload($payload);
        $task->setTask($taskHandler::getName());

        $this->em->persist($task);
        $this->em->flush($task);

        $this->bus->dispatch(new RunOperationTask($task->getId()));

        return $task;
    }

    public function handleTask(OperationTask $task): void
    {
        if (!$this->start($task)) {
            // Cancelled (or already run) before the worker picked it up
            $this->logger->info(sprintf('Skipping task "%s" (%s): it is no longer pending', $task->getTask(), $task->getId()));

            return;
        }

        $output = new BufferedOutput();
        $context = new RunContext(
            $task,
            $this->em,
            $output,
            new StreamOutput(fopen('/dev/null', 'w'))
        );
        $taskHandler = $this->taskRegistry->getTask($task->getTask());

        try {
            try {
                $taskHandler->handle($task->getPayload(), $context);
            } catch (OperationTaskCancelledException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $task = self::reloadTask($this->em, $task);
                $task->setStatus(OperationTask::STATUS_FAILED);
                $task->setEndedAt(new \DateTimeImmutable());
                $output->writeln($e->getMessage());
                $task->appendOutput($output->fetch());
                $this->em->persist($task);
                $this->em->flush();

                $this->logger->error(sprintf('Failed running task "%s" (%s): %s', $task->getTask(), $task->getId(), $e->getMessage()), [
                    'exception' => $e,
                ]);

                return;
            }

            $task = self::reloadTask($this->em, $task);
        } catch (OperationTaskCancelledException) {
            // Cancellation is not an error: the task row already holds its final state
            $this->logger->info(sprintf('Task "%s" (%s) has been cancelled', $task->getTask(), $task->getId()));

            return;
        }

        $task->appendOutput($output->fetch());

        if ($context->isCompletionDeferred()) {
            // The task fans out its work over asynchronous sub-messages that
            // will complete it once every item has been processed.
            $this->em->persist($task);
            $this->em->flush();

            return;
        }

        $task->setStatus(OperationTask::STATUS_COMPLETED);
        $task->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($task);
        $this->em->flush();
    }

    /**
     * Atomically moves the task from PENDING to IN_PROGRESS, so that a cancellation
     * requested meanwhile is never overwritten.
     */
    private function start(OperationTask $task): bool
    {
        $updated = $this->em->createQuery(
            sprintf(
                'UPDATE %s t SET t.status = :inProgress, t.startedAt = :now WHERE t.id = :id AND t.status = :pending',
                OperationTask::class
            )
        )
            ->setParameter('inProgress', OperationTask::STATUS_IN_PROGRESS)
            ->setParameter('pending', OperationTask::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $task->getId())
            ->execute();

        $this->em->refresh($task);

        return $updated > 0;
    }

    /**
     * Reads the task state from the database: the entity manager of a long-running
     * worker would otherwise keep serving the state it loaded before a cancellation.
     *
     * @throws OperationTaskCancelledException
     */
    public static function reloadTask(EntityManagerInterface $em, OperationTask $task): OperationTask
    {
        if ($em->contains($task)) {
            $em->refresh($task);
        } else {
            $task = $em->find(OperationTask::class, $task->getId()) ?? throw new \RuntimeException(sprintf('Task "%s" not found', $task->getId()));
        }

        if (OperationTask::STATUS_CANCELLED === $task->getStatus()) {
            throw new OperationTaskCancelledException($task->getId());
        }

        return $task;
    }
}
