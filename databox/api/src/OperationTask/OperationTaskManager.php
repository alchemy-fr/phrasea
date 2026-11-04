<?php

namespace App\OperationTask;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\Handler\RunOperationTask;
use App\Entity\Admin\OperationTask;
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
        JwtUser $user,
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
        $output = new BufferedOutput();
        $context = new RunContext(
            $task,
            $this->em,
            $output,
            new StreamOutput(fopen('/dev/null', 'w'))
        );
        $taskHandler = $this->taskRegistry->getTask($task->getTask());

        $task->setStartedAt(new \DateTimeImmutable());
        $task->setStatus(OperationTask::STATUS_IN_PROGRESS);
        $this->em->persist($task);
        $this->em->flush();

        try {
            $taskHandler->handle($task->getPayload(), $context);
        } catch (\Throwable $e) {
            $task = $this->reload($task);
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

        $task = $this->reload($task);
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

    private function reload(OperationTask $task): OperationTask
    {
        $task = $this->em->find(OperationTask::class, $task->getId());
        if (!$task) {
            throw new \RuntimeException('Task not found');
        }

        if (OperationTask::STATUS_CANCELLED === $task->getStatus()) {
            throw new \RuntimeException('Task cancelled');
        }

        return $task;
    }
}
