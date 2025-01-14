<?php

namespace App\Listener;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: WorkflowUpdateEvent::class, method: 'onWorkflowUpdate')]
final readonly class AssetIngestWorkflowListener
{
    public function __construct(
        private PusherManager $pusherManager,
    ) {
    }

    public function onWorkflowUpdate(WorkflowUpdateEvent $event): void
    {
        $state = $event->getState();
        if (str_starts_with($state->getWorkflowName(), 'asset-ingest:') && in_array($state->getStatus(), [
            WorkflowState::STATUS_SUCCESS,
            WorkflowState::STATUS_FAILURE,
        ])) {
            $assetId = $state->getEvent()->getInputs()['assetId'];
            $this->pusherManager->trigger('asset-'.$assetId, 'asset_ingested', [], direct: true);
        }
    }
}
