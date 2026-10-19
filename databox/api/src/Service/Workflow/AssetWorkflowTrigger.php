<?php

declare(strict_types=1);

namespace App\Service\Workflow;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;

/**
 * Runs the ingest workflow of an asset again.
 */
final readonly class AssetWorkflowTrigger
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
    ) {
    }

    public function trigger(Asset $asset, string $initiatorId): void
    {
        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $initiatorId,
            ]
        );
    }
}
