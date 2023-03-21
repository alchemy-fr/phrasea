<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Executor\WorkflowExecutionContext;

interface RunnerInterface
{
    public function run(WorkflowExecutionContext $workflowContext, string $jobId): void;
}
