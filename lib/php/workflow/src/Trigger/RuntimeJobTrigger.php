<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Runner\RunnerInterface;

class RuntimeJobTrigger implements JobTriggerInterface
{
    public function __construct(private readonly RunnerInterface $runner)
    {
    }

    public function triggerJob(string $workflowId, string $jobId): bool
    {
        $this->runner->run($workflowId, $jobId);

        return true;
    }
}
