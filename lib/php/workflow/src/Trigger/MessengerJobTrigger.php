<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Message\JobConsumer;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerJobTrigger implements JobTriggerInterface
{
    public function __construct(
        private MessageBusInterface $bus
    )
    {
    }

    public function triggerJob(JobTrigger $jobTrigger): void
    {
        $this->bus->dispatch(new JobConsumer($jobTrigger->getWorkflowId(), $jobTrigger->getJobId(), $jobTrigger->getJobStateId()));
    }

    public function shouldContinue(): bool
    {
        return true;
    }

    public function isSynchronous(): bool
    {
        return false;
    }
}
