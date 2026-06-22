<?php

namespace App\AdminTask;

use App\Consumer\Handler\RunAdminTask;
use App\Entity\Admin\AdminTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AdminTaskManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private AdminTaskRegistry $taskRegistry,
    ) {
    }

    /**
     * @template T of AdminTaskInterface
     *
     * @param class-string<T> $taskClass
     */
    public function createTask(
        string $taskClass,
        ?array $payload = [],
        ?string $name = null,
    ): AdminTask {
        $task = new AdminTask();
        $task->setName($name ?? $taskClass::getDefaultName());
        $task->setPayload($payload);

        $this->em->persist($task);
        $this->em->flush($task);

        $this->bus->dispatch(new RunAdminTask($task->getId()));

        return $task;
    }

    public function handleTask(AdminTask $task): void
    {
        $context = new RunContext($this->em);
        $taskHandler = $this->taskRegistry->getTask($task->getTask());

        $taskHandler->handle($task->getPayload(), $context);

        $this->em->persist($task);
    }
}
