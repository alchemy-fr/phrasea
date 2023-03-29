<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class ArthemRabbitJobTrigger implements JobTriggerInterface
{
    private EventProducer $producer;

    public function __construct(EventProducer $producer)
    {
        $this->producer = $producer;
    }

    public function triggerJob(string $workflowId, string $jobId): bool
    {
        $this->producer->publish(new ());

        return true;
    }
}
