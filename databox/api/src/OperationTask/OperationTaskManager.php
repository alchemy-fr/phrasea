<?php

namespace App\OperationTask;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\Handler\RunOperationTask;
use App\Entity\Admin\OperationTask;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        JwtUser $user,
        string $taskName,
        ?array $payload = [],
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
        $context = new RunContext($this->em);
        $taskHandler = $this->taskRegistry->getTask($task->getTask());

        $task->setStatus(OperationTask::STATUS_IN_PROGRESS);
        $this->em->persist($task);
        $this->em->flush();

        try {
            $taskHandler->handle($task->getPayload(), $context);
            $task->setStatus(OperationTask::STATUS_COMPLETED);
        } catch (\Throwable $e) {
            $task->setStatus(OperationTask::STATUS_FAILED);

            $this->logger->error(sprintf('Failed running task "%s" (%s): %s', $task->getTask(), $task->getId(), $e->getMessage()), [
                'exception' => $e,
            ]);
        } finally {
            $task->setEndedAt(new \DateTimeImmutable());
        }

        $this->em->persist($task);
        $this->em->flush();
    }
}
