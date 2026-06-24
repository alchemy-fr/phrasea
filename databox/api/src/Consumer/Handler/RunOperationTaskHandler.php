<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Admin\OperationTask;
use App\OperationTask\OperationTaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RunOperationTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private OperationTaskManager $taskManager,
    ) {
    }

    public function __invoke(RunOperationTask $message): void
    {
        $task = $this->em->find(OperationTask::class, $message->id);
        if (!$task) {
            // Task cancelled
            return;
        }

        $this->taskManager->handleTask($task);
    }
}
