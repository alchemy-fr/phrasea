<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use App\Realtime\RealtimeChannel;
use App\Service\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Tells the viewers of an asset that one of its workflow runs started or ended,
 * so that they reload its runs.
 *
 * The asset channel only requires VIEW while the runs require EDIT: the payload stays empty.
 */
#[AsEventListener(event: WorkflowUpdateEvent::class, method: 'onWorkflowUpdate')]
final readonly class AssetWorkflowRealtimeListener
{
    public function __construct(
        private PusherManager $pusherManager,
    ) {
    }

    public function onWorkflowUpdate(WorkflowUpdateEvent $event): void
    {
        $workflowEvent = $event->getState()->getEvent();
        if (null === $workflowEvent || IncomingUploaderFileWorkflowEvent::EVENT === $workflowEvent->getName()) {
            return;
        }

        $assetId = $workflowEvent->getInputs()['assetId'] ?? null;
        if (null === $assetId) {
            return;
        }

        $this->pusherManager->trigger(RealtimeChannel::asset($assetId), 'workflow_update', []);
    }
}
