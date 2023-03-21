<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Executor\WorkflowExecutionContext;

class RuntimeRunner implements RunnerInterface
{
    private PlanExecutor $planExecutor;

    public function __construct(PlanExecutor $planExecutor)
    {
        $this->planExecutor = $planExecutor;
    }

    public function run(WorkflowExecutionContext $workflowContext, string $jobId): void
    {
        $this->planExecutor->executePlan($workflowContext, $jobId);
    }
}
