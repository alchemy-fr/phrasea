<?php

namespace Alchemy\WorkflowBundle\Listener;

use Alchemy\Workflow\Listener\JobUpdateEvent;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use Alchemy\WorkflowBundle\Message\JobUpdatePusherMessage;
use Alchemy\WorkflowBundle\Message\WorkflowUpdatePusherMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(JobUpdateEvent::class, method: 'jobUpdate')]
#[AsEventListener(WorkflowUpdateEvent::class, method: 'workflowUpdate')]
final readonly class PusherListener
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function jobUpdate(JobUpdateEvent $event): void
    {
        $this->bus->dispatch(new JobUpdatePusherMessage(
            $event->getWorkflowId(),
            $event->getJobId(),
            $event->getStatus(),
        ));
    }

    public function workflowUpdate(WorkflowUpdateEvent $event): void
    {
        $state = $event->getState();

        $this->bus->dispatch(new WorkflowUpdatePusherMessage(
            $state->getId(),
            $state->getStatus(),
        ));
    }
}
