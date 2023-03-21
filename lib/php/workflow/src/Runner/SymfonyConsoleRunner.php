<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Executor\WorkflowExecutionContext;

class SymfonyConsoleRunner implements RunnerInterface
{
    public function run(WorkflowExecutionContext $workflowContext, string $jobId): void
    {
        exec(sprintf('bin/console workflow:run "%s"', escapeshellarg($jobId)));
    }
}
