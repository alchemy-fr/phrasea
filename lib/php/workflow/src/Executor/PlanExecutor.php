<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;

final readonly class PlanExecutor
{
    public function __construct(
        private WorkflowRepositoryInterface $workflowRepository,
        private JobExecutor $jobExecutor,
        private StateRepositoryInterface $stateRepository,
    )
    {
    }

    public function executePlan(string $workflowId, string $jobId): void
    {
        $workflowState = $this->stateRepository->getWorkflowState($workflowId);

        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $this->jobExecutor->executeJob($workflowState, $plan->getJob($jobId));
    }
}
