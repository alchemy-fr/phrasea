<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Runner\RunnerInterface;

readonly class RuntimeJobTrigger implements JobTriggerInterface
{
    public function __construct(private RunnerInterface $runner)
    {
    }

    public function triggerJob(JobTrigger $jobTrigger): void
    {
        $this->runner->run($jobTrigger);
    }

    public function shouldContinue(): bool
    {
        return true;
    }

    public function isSynchronous(): bool
    {
        return true;
    }
}
