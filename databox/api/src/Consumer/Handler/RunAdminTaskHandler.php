<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\AdminTask\AdminTaskManager;
use App\Entity\Admin\AdminTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RunAdminTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdminTaskManager $taskManager,
    ) {
    }

    public function __invoke(RunAdminTask $message): void
    {
        $task = $this->em->find(AdminTask::class, $message->id);
        if (!$task) {
            // Task cancelled
            return;
        }

        $this->taskManager->handleTask($task);
    }
}
