<?php

namespace Alchemy\WorkflowBundle\Listener;

use Alchemy\Workflow\Listener\JobUpdateEvent;
use Pusher\Pusher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(JobUpdateEvent::class, method: 'jobUpdate')]
final readonly class PusherListener
{
    public function __construct(
        private Pusher $pusher,
        private string $channelPrefix = 'workflow-',
    ) {
    }

    public function jobUpdate(JobUpdateEvent $event): void
    {
        $this->pusher->trigger($this->channelPrefix.$event->getWorkflowId(), 'job_update', [
            'jobId' => $event->getJobId(),
            'status' => $event->getStatus(),
        ]);
    }
}
