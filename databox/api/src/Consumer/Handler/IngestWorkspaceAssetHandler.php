<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Admin\OperationTask;
use App\Entity\Core\Asset;
use App\OperationTask\OperationTaskProgress;
use App\Service\Asset\WorkspaceAssetIngester;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestWorkspaceAssetHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceAssetIngester $ingester,
        private OperationTaskProgress $taskProgress,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IngestWorkspaceAsset $message): void
    {
        $task = $this->em->find(OperationTask::class, $message->taskId);
        if (!$task || in_array($task->getStatus(), [
            OperationTask::STATUS_CANCELLED,
            OperationTask::STATUS_FAILED,
            OperationTask::STATUS_COMPLETED,
        ], true)) {
            // Task is gone or no longer running: drop the asset silently.
            return;
        }

        $asset = $this->em->find(Asset::class, $message->assetId);
        if (null !== $asset) {
            try {
                $this->ingester->ingestAsset($asset, $task->getOwnerId());
            } catch (\Throwable $e) {
                // A single failing asset must not stall the whole task.
                $this->logger->error(sprintf('Failed to trigger ingest for asset "%s": %s', $message->assetId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }

        $this->taskProgress->increment($message->taskId);
        $this->taskProgress->finalizeIfComplete($message->taskId);
    }
}
