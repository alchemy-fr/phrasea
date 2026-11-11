<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Trigger;

use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\Trigger\JobTriggerInterface;

/**
 * Keeps the triggered jobs, as an asynchronous message bus would, until the test consumes them.
 */
final class QueuedJobTrigger implements JobTriggerInterface
{
    /**
     * @var JobTrigger[]
     */
    private array $queue = [];

    public function triggerJob(JobTrigger $jobTrigger): void
    {
        $this->queue[] = $jobTrigger;
    }

    public function shift(): ?JobTrigger
    {
        return array_shift($this->queue);
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
