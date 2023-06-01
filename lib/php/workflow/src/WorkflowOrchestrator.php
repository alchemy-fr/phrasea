<?php

declare(strict_types=1);

namespace Alchemy\Workflow;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Trigger\JobTriggerInterface;
use Alchemy\Workflow\Validator\EventValidatorInterface;

class WorkflowOrchestrator
{
    private WorkflowRepositoryInterface $workflowRepository;
    private StateRepositoryInterface $stateRepository;
    private JobTriggerInterface $trigger;
    private EventValidatorInterface $eventValidator;

    public function __construct(
        WorkflowRepositoryInterface $workflowRepository,
        StateRepositoryInterface $stateRepository,
        JobTriggerInterface $trigger,
        EventValidatorInterface $eventValidator,
    )
    {
        $this->workflowRepository = $workflowRepository;
        $this->stateRepository = $stateRepository;
        $this->trigger = $trigger;
        $this->eventValidator = $eventValidator;
    }

    /**
     * @return int The number of triggered workflows
     */
    public function dispatchEvent(WorkflowEvent $event, array $context = []): int
    {
        $workflows = $this->workflowRepository->getWorkflowsByEvent($event);
        foreach ($workflows as $workflow) {
            $this->validateEvent($event, $workflow);
        }

        $i = 0;
        foreach ($workflows as $workflow) {
            $this->startWorkflow($workflow->getName(), $event, $context);
            ++$i;
        }

        return $i;
    }

    private function validateEvent(WorkflowEvent $event, Workflow $workflow): void
    {
        foreach ($workflow->getOn() as $e => $on) {
            if ($e === $event->getName()) {
                $this->eventValidator->validateEvent($on, $event);

                return;
            }
        }
    }

    public function startWorkflow(string $workflowName, ?WorkflowEvent $event = null, array $context = []): WorkflowState
    {
        $workflowState = new WorkflowState(
            $this->stateRepository,
            $workflowName,
            $event,
            null,
            $context
        );

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
        $planner = new WorkflowPlanner([$this->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        [$nextJobId, $workflowEndStatus] = $this->getNextJob($plan, $workflowState);
        if (null !== $nextJobId) {
            do {
                $continue = $this->triggerJob($workflowState, $nextJobId);

                if ($continue) {
                    [$nextJobId, $workflowEndStatus] = $this->getNextJob($plan, $workflowState);
                    if (null === $nextJobId) {
                        $continue = false;
                    }
                }
            } while ($continue);
        }

        if (null !== $workflowEndStatus) {
            $workflowState->setEndedAt(new MicroDateTime());
            $workflowState->setStatus($workflowEndStatus);
            $this->stateRepository->persistWorkflowState($workflowState);
        }
    }

    public function retryFailedJobs(string $workflowId, ?string $jobIdFilter = null): void
    {
        $this->rerunJobs($workflowId, $jobIdFilter, [
            JobState::STATUS_FAILURE,
            JobState::STATUS_ERROR,
        ]);
    }

    public function rerunJobs(string $workflowId, ?string $jobIdFilter = null, ?array $expectedStatuses = null): void
    {
        $workflowState = $this->stateRepository->getWorkflowState($workflowId);

        $event = $workflowState->getEvent();

        $planner = new WorkflowPlanner([$this->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $jobsToTrigger = [];

        foreach ($plan->getStages() as $stage) {
            foreach ($stage->getRuns() as $run) {
                $jobId = $run->getJob()->getId();

                if (null !== $jobIdFilter && $jobIdFilter !== $jobId) {
                    continue;
                }

                $jobState = $this->stateRepository->getJobState($workflowId, $jobId);
                if (null === $expectedStatuses || (null !== $jobState && in_array($jobState->getStatus(), $expectedStatuses, true))) {
                    $this->stateRepository->removeJobState($workflowId, $jobId);

                    $jobsToTrigger[] = $jobId;
                }

                if (null !== $jobIdFilter) {
                    break 2;
                }
            }
        }

        if (!empty($jobsToTrigger)) {
            $workflowState->setStatus(WorkflowState::STATUS_STARTED);
            $this->stateRepository->persistWorkflowState($workflowState);

            foreach ($jobsToTrigger as $jobId) {
                $this->triggerJob($workflowState, $jobId);
            }
        }
    }

    private function loadWorkflowByName(string $name): Workflow
    {
        $workflow = $this->workflowRepository->loadWorkflowByName($name);
        if (null === $workflow) {
            throw new \RuntimeException(sprintf('Workflow "%s" not found', $name));
        }

        return $workflow;
    }

    /**
     * @return [?string, ?int] [the next job ID, The Workflow Status]
     */
    private function getNextJob(Plan $plan, WorkflowState $state): array
    {
        $statuses = [];
        $hasFailedJob = false;

        $workflowComplete = true;
        foreach ($plan->getStages() as $stage) {
            $stageComplete = true;

            foreach ($stage->getRuns() as $run) {
                $job = $run->getJob();
                $jobId = $job->getId();

                $jobState = $this->stateRepository->getJobState($state->getId(), $jobId);
                if (null === $jobState) {
                    if ($this->satisfiesAllNeeds($statuses, $job)) {
                        return [$jobId, null];
                    } else {
                        continue;
                    }
                }

                $statuses[$jobId] = $jobState->getStatus();

                if ($jobState->getStatus() === JobState::STATUS_FAILURE) {
                    $hasFailedJob = true;
                    if (!$job->isContinueOnError()) {
                        return [null, WorkflowState::STATUS_FAILURE];
                    }
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
                $workflowComplete = false;
            }
        }

        return $workflowComplete ? [null, $hasFailedJob ? WorkflowState::STATUS_FAILURE : WorkflowState::STATUS_SUCCESS] : [null, null];
    }

    private function satisfiesAllNeeds(array $states, Job $job): bool
    {
        foreach ($job->getNeeds() as $need) {
            if (!isset($states[$need]) || $states[$need] !== JobState::STATUS_SUCCESS) {
                return false;
            }
        }

        return true;
    }

    private function triggerJob(WorkflowState $state, string $jobId): bool
    {
        $workflowId = $state->getId();

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->acquireJobLock($workflowId, $jobId);
        }

        $jobState = new JobState($workflowId, $jobId, JobState::STATUS_TRIGGERED);
        $this->stateRepository->persistJobState($jobState);

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->releaseJobLock($workflowId, $jobId);
        }

        return $this->trigger->triggerJob($state->getId(), $jobId);
    }
}
