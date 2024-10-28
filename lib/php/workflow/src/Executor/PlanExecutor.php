<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;

final readonly class PlanExecutor
{
    public function __construct(
        private WorkflowRepositoryInterface $workflowRepository,
        private JobExecutor $jobExecutor,
        private StateRepositoryInterface $stateRepository,
    ) {
    }

    public function executePlan(string $workflowId, string $jobStateId): void
    {
        $workflowState = $this->stateRepository->getWorkflowState($workflowId);

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->acquireJobLock($workflowId, $jobStateId);
        }

        $jobState = $this->stateRepository->getJobState($workflowId, $jobStateId);
        if (null === $jobState) {
            throw new \InvalidArgumentException(sprintf('Job state "%s" does not exists for workflow "%s"', $jobStateId, $workflowId));
        }

        $jobId = $jobState->getJobId();

        $event = $workflowState->getEvent();
        $workflow = $this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName());
        if (null === $workflow) {
            throw new \RuntimeException(sprintf('Workflow "%s" not found', $workflowState->getWorkflowName()));
        }

        $planner = new WorkflowPlanner([$workflow]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $this->jobExecutor->executeJob($workflowState, $plan->getJob($jobId), $jobState, $workflow->getEnv()->getArrayCopy());


    }
}
