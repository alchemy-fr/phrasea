<?php

declare(strict_types=1);

namespace Alchemy\Workflow;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Trigger\JobTriggerInterface;

class WorkflowOrchestrator
{
    private WorkflowRepositoryInterface $workflowRepository;
    private StateRepositoryInterface $stateRepository;
    private JobTriggerInterface $trigger;

    public function __construct(
        WorkflowRepositoryInterface $workflowRepository,
        StateRepositoryInterface $stateRepository,
        JobTriggerInterface $trigger
    )
    {
        $this->workflowRepository = $workflowRepository;
        $this->stateRepository = $stateRepository;
        $this->trigger = $trigger;
    }

    public function startWorkflow(string $workflowName, ?WorkflowEvent $event = null): WorkflowState
    {
        $workflowState = new WorkflowState($workflowName, $event);

        $this->stateRepository->persistWorkflowState($workflowState);

        $this->continueWorkflow($workflowState->getId(), $workflowState);

        return $workflowState;
    }

    public function continueWorkflow(string $workflowId, ?WorkflowState $workflowState = null): void
    {
        if (null === $workflowState) {
            $workflowState = $this->stateRepository->getWorkflowState($workflowId);
        }

        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $nextJobId = $this->getNextJob($plan, $workflowState);
        do {
            $continue = $this->triggerJob($workflowState, $plan, $nextJobId);

            if ($continue) {
                if (null === $nextJobId = $this->getNextJob($plan, $workflowState)) {
                    $continue = false;
                }
            }
        } while ($continue);

        $this->stateRepository->persistWorkflowState($workflowState);
    }

    private function getNextJob(Plan $plan, WorkflowState $state): ?string
    {
        $resultList = $this->stateRepository->getJobResultList($state->getId());

        foreach ($plan->getStages() as $stage) {
            foreach ($stage->getRuns() as $run) {
                $jobId = $run->getJob()->getId();
                if (!$resultList->hasJobResult($jobId)) {
                    return $jobId;
                }
            }
        }

        return null;
    }

    private function triggerJob(WorkflowState $state, Plan $plan, string $jobId): bool
    {
        $workflowId = $state->getId();
        $job = $plan->getJob($jobId);

        if (null !== $job->getIf()) {
            $this->createJobState($workflowId, $jobId, JobState::STATUS_SKIPPED);

            return true;
        }

        $this->createJobState($workflowId, $jobId, JobState::STATUS_TRIGGERED);

        return $this->trigger->triggerJob($state->getId(), $jobId);
    }

    private function createJobState(string $workflowId, string $jobId, int $status): void
    {
        $this->stateRepository->acquireJobLock($workflowId, $jobId);
        $jobState = new JobState($workflowId, $jobId, $status);
        $this->stateRepository->persistJobState($jobState);
        $this->stateRepository->releaseJobLock($workflowId, $jobId);
    }
}
