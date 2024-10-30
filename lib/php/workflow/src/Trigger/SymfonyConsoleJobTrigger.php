<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

class SymfonyConsoleJobTrigger implements JobTriggerInterface
{
    public function triggerJob(JobTrigger $jobTrigger): void
    {
        exec(sprintf('bin/console workflow:run "%s"', escapeshellarg($jobTrigger->getJobStateId())));
    }

    public function shouldContinue(): bool
    {
        return false;
    }

    public function isSynchronous(): bool
    {
        return true;
    }
}
