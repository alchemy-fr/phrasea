<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Runner\RunnerInterface;

readonly class RuntimeJobTrigger implements JobTriggerInterface
{
    public function __construct(private RunnerInterface $runner)
    {
    }

    public function triggerJob(string $workflowId, string $jobId): bool
    {
        $this->runner->run($workflowId, $jobId);

        return true;
    }
}
