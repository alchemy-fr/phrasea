<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Border\WorkspaceFileReanalyzer;
use App\Entity\Admin\OperationTask;
use App\Entity\Core\File;
use App\OperationTask\OperationTaskProgress;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReanalyzeWorkspaceFileHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceFileReanalyzer $reanalyzer,
        private OperationTaskProgress $taskProgress,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReanalyzeWorkspaceFile $message): void
    {
        $task = $this->em->find(OperationTask::class, $message->taskId);
        if (!$task || in_array($task->getStatus(), [
            OperationTask::STATUS_CANCELLED,
            OperationTask::STATUS_FAILED,
            OperationTask::STATUS_COMPLETED,
        ], true)) {
            // Task is gone or no longer running: drop the file silently.
            return;
        }

        $file = $this->em->find(File::class, $message->fileId);
        if (null !== $file) {
            try {
                $this->reanalyzer->reanalyzeFile($file);
                $this->em->flush();
            } catch (\Throwable $e) {
                // A single failing file must not stall the whole task.
                $this->logger->error(sprintf('Failed to re-analyze file "%s": %s', $message->fileId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }

        $this->taskProgress->increment($message->taskId);
        $this->taskProgress->finalizeIfComplete($message->taskId);
    }
}
