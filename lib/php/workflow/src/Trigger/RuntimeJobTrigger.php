<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Runner\RunnerInterface;

class RuntimeJobTrigger implements JobTriggerInterface
{
    private RunnerInterface $runner;

    public function __construct(RunnerInterface $runner)
    {
        $this->runner = $runner;
    }

    public function triggerJob(string $workflowId, string $jobId): bool
    {
        $this->runner->run($workflowId, $jobId);

        return true;
    }
}
