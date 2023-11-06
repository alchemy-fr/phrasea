<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Consumer\JobConsumer;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class ArthemRabbitJobTrigger implements JobTriggerInterface
{
    public function __construct(private readonly EventProducer $producer)
    {
    }

    public function triggerJob(string $workflowId, string $jobId): bool
    {
        $this->producer->publish(JobConsumer::createEvent($workflowId, $jobId));

        return true;
    }
}
