<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Admin\OperationTask;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\OperationTask\OperationTaskProgress;
use App\Service\Asset\Attribute\AttributeDefinitionOperationRunner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplyAttributeDefinitionOperationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeDefinitionOperationRunner $runner,
        private OperationTaskProgress $taskProgress,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ApplyAttributeDefinitionOperation $message): void
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
        $definition = $this->em->find(AttributeDefinition::class, $message->definitionId);

        if (null !== $asset && null !== $definition) {
            try {
                $this->runner->applyToAsset($message->operation, $asset, $definition);
            } catch (\Throwable $e) {
                // A single failing asset must not stall the whole task.
                $this->logger->error(sprintf('Failed to apply operation "%s" to asset "%s": %s', $message->operation, $message->assetId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }

        $this->taskProgress->increment($message->taskId);
        $this->taskProgress->finalizeIfComplete($message->taskId);
    }
}
