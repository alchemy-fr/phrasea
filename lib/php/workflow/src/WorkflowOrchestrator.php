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
        $workflowState = new WorkflowState($this->stateRepository, $workflowName, $event);

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

        [$nextJobId, $completeStatus] = $this->getNextJob($plan, $workflowState);
        if (null !== $nextJobId) {
            do {
                $continue = $this->triggerJob($workflowState, $plan, $nextJobId);

                if ($continue) {
                    [$nextJobId, $completeStatus] = $this->getNextJob($plan, $workflowState);
                    if (null === $nextJobId) {
                        $continue = false;
                    }
                }
            } while ($continue);
        }

        if (null !== $completeStatus) {
            $workflowState->setEndedAt(new \DateTimeImmutable());
            $workflowState->setStatus($completeStatus ? WorkflowState::STATUS_SUCCESS : WorkflowState::STATUS_FAILURE);
            $this->stateRepository->persistWorkflowState($workflowState);
        }
    }

    /**
     * @return [?string, ?bool] [the next job ID, isWorkflowComplete]
     */
    private function getNextJob(Plan $plan, WorkflowState $state): array
    {
        foreach ($plan->getStages() as $stage) {
            $stageComplete = true;

            foreach ($stage->getRuns() as $run) {
                $job = $run->getJob();
                $jobId = $job->getId();

                $jobState = $this->stateRepository->getJobState($state->getId(), $jobId);
                if (null === $jobState) {
                    return [$jobId, null];
                }

                if ($jobState->getStatus() === JobState::STATUS_FAILURE && !$job->isContinueOnError()) {
                    return [null, false];
                }

                if (!in_array($jobState->getStatus(), [
                    JobState::STATUS_SUCCESS,
                    JobState::STATUS_SKIPPED,
                    JobState::STATUS_FAILURE,
                ], true)) {
                    $stageComplete = false;
                }
            }

            if (!$stageComplete) {
                return [null, null];
            }
        }

        return [null, true];
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
