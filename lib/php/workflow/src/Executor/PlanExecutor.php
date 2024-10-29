<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\Trigger\JobTrigger;

final readonly class PlanExecutor
{
    public function __construct(
        private WorkflowRepositoryInterface $workflowRepository,
        private JobExecutor $jobExecutor,
        private StateRepositoryInterface $stateRepository,
    ) {
    }

    public function executePlan(JobTrigger $jobTrigger): void
    {
        $workflowState = $this->stateRepository->getWorkflowState($jobTrigger->getWorkflowId());

        $event = $workflowState->getEvent();
        $workflow = $this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName());
        if (null === $workflow) {
            throw new \RuntimeException(sprintf('Workflow "%s" not found', $workflowState->getWorkflowName()));
        }

        $planner = new WorkflowPlanner([$workflow]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $this->jobExecutor->executeJob($workflowState, $plan->getJob($jobTrigger->getJobId()), $jobTrigger->getJobStateId(), $workflow->getEnv()->getArrayCopy());
    }
}
