<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Message\JobConsumer;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerJobTrigger implements JobTriggerInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function triggerJob(string $workflowId, string $jobStateId): bool
    {
        $this->bus->dispatch(new JobConsumer($workflowId, $jobStateId));

        return true;
    }
}
