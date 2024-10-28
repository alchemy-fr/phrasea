<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Executor\PlanExecutor;

readonly class RuntimeRunner implements RunnerInterface
{
    public function __construct(private PlanExecutor $planExecutor)
    {
    }

    public function run(string $workflowId, string $jobStateId): void
    {
        $this->planExecutor->executePlan($workflowId, $jobStateId);
    }
}
