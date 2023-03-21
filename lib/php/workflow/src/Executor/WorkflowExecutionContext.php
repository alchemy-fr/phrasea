<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class WorkflowExecutionContext
{
    private WorkflowState $state;
    private Plan $plan;

    private $next;
    public function __construct(WorkflowState $state, Plan $plan, callable $next)
    {
        $this->state = $state;
        $this->plan = $plan;
        $this->next = $next;
    }

    public function getState(): WorkflowState
    {
        return $this->state;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function continueWorkflow(): void
    {
        $next = $this->next;
        $next();
    }

    public function setJobTriggered(string $jobId): void
    {
        $this->state->getJobResults()->setJobResult($jobId, new JobState(JobState::STATE_TRIGGERED));
    }
}
