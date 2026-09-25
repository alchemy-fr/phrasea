<?php

namespace Alchemy\WorkflowBundle\Listener;

use Alchemy\Workflow\Listener\JobUpdateEvent;
use Alchemy\WorkflowBundle\Message\JobUpdatePusherMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(JobUpdateEvent::class, method: 'jobUpdate')]
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
}
