<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

class SymfonyConsoleJobTrigger implements JobTriggerInterface
{
    public function triggerJob(string $workflowId, string $jobStateId): bool
    {
        exec(sprintf('bin/console workflow:run "%s"', escapeshellarg($jobStateId)));

        return false;
    }
}
