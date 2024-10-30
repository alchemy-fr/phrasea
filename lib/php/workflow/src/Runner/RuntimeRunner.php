<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Trigger\JobTrigger;

readonly class RuntimeRunner implements RunnerInterface
{
    public function __construct(private PlanExecutor $planExecutor)
    {
    }

    public function run(JobTrigger $jobTrigger): void
    {
        $this->planExecutor->executePlan($jobTrigger);
    }
}
