<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Consumer\Handler\IngestWorkspaceAsset;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\OperationTask\OperationTaskProgress;
use App\OperationTask\RunContext;
use App\Repository\Core\AssetRepository;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class WorkspaceAssetIngester
{
    public function __construct(
        private AssetRepository $assetRepository,
        private WorkflowOrchestrator $workflowOrchestrator,
        private MessageBusInterface $bus,
        private OperationTaskProgress $taskProgress,
    ) {
    }

    /**
     * Fan out the ingest: dispatch one message per asset so the main task
     * worker is released immediately. Each message triggers the ingest
     * workflow for its asset and updates the task progress; the last one
     * completes the task.
     */
    public function ingestWorkspaceAssets(string $workspaceId, RunContext $context): void
    {
        $taskId = $context->getTaskId();
        $this->taskProgress->init($taskId);

        $dispatched = 0;
        foreach ($this->assetRepository->iterateIdsByWorkspace($workspaceId) as $assetId) {
            $this->bus->dispatch(new IngestWorkspaceAsset($taskId, $assetId));
            ++$dispatched;
        }

        if (0 === $dispatched) {
            return;
        }

        $context->getOutput()->writeln(sprintf('<info>Dispatched %d asset(s) for ingest.</info>', $dispatched));

        // Publish the authoritative total, then complete the task in case
        // every dispatched message was already processed.
        $this->taskProgress->setItemTotal($taskId, $dispatched);
        $this->taskProgress->finalizeIfComplete($taskId);

        $context->deferCompletion();
    }

    /**
     * Trigger the ingest workflow for a single asset.
     */
    public function ingestAsset(Asset $asset, ?string $ownerId): void
    {
        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $ownerId ?? $asset->getOwnerId(),
            ]
        );
    }
}
