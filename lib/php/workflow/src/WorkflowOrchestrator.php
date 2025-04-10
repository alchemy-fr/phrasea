<?php

declare(strict_types=1);

namespace Alchemy\Workflow;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\Repository\TransactionalStateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\Trigger\JobTriggerInterface;
use Alchemy\Workflow\Validator\EventValidatorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class WorkflowOrchestrator
{
    /**
     * @var JobTrigger[]
     */
    private array $workflowsToTrigger = [];

    public function __construct(
        private readonly WorkflowRepositoryInterface $workflowRepository,
        private readonly StateRepositoryInterface $stateRepository,
        private readonly JobTriggerInterface $trigger,
        private readonly EventValidatorInterface $eventValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
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

        $this->doContinueWorkflow($workflowState);

        $this->flush();

        return $workflowState;
    }

    public function cancelWorkflow(string $workflowId): void
    {
        $this->wrapInTransaction(function () use ($workflowId): void {
            if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
                $this->stateRepository->acquireWorkflowLock($workflowId);
            }

            $workflowState = $this->stateRepository->getWorkflowState($workflowId);

            if (null !== $workflowState->getEndedAt()) {
                return;
            }

            if (WorkflowState::STATUS_FAILURE === $workflowState->getStatus()) {
                return;
            }

            $workflowState->setCancelledAt(new MicroDateTime());
            $workflowState->setStatus(WorkflowState::STATUS_CANCELLED);

            $workflow = $this->loadWorkflowByName($workflowState->getWorkflowName());

            foreach ($workflow->getJobIds() as $jobId) {
                $jobState = $this->stateRepository->getLastJobState($workflowId, $jobId);
                if (null !== $jobState && in_array($jobState->getStatus(), [
                    JobState::STATUS_TRIGGERED,
                    JobState::STATUS_RUNNING,
                ], true)) {
                    $jobState->setStatus(JobState::STATUS_CANCELLED);
                    $this->stateRepository->persistJobState($jobState);
                }
            }

            $this->stateRepository->persistWorkflowState($workflowState);

            if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
                $this->stateRepository->releaseWorkflowLock($workflowId);
            }
        });
    }

    public function continueWorkflow(string $workflowId): void
    {
        $this->wrapInTransaction(function () use ($workflowId): void {
            if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
                $this->stateRepository->acquireWorkflowLock($workflowId);
            }

            $workflowState = $this->stateRepository->getWorkflowState($workflowId);
            if ($workflowState->isCancelled()) {
                return;
            }

            $this->doContinueWorkflow($workflowState);

            if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
                $this->stateRepository->releaseWorkflowLock($workflowId);
            }
        });

        $this->flush();
    }

    private function wrapInTransaction(callable $callback): void
    {
        if ($this->stateRepository instanceof TransactionalStateRepositoryInterface) {
            $this->stateRepository->transactional($callback);

            return;
        }

        $callback();
    }

    private function doContinueWorkflow(WorkflowState $workflowState): void
    {
        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        [$nextJobId, $workflowEndStatus] = $this->getNextJob($plan, $workflowState);
        if (null !== $nextJobId) {
            $continue = $this->trigger->shouldContinue();

            do {
                $this->triggerJob($workflowState, $nextJobId);

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

            $this->eventDispatcher->dispatch(new WorkflowUpdateEvent($workflowState));
        }
    }

    public function retryFailedJobs(string $workflowId, ?string $jobIdFilter = null): void
    {
        $this->rerunJobs($workflowId, $jobIdFilter, [
            JobState::STATUS_FAILURE,
            JobState::STATUS_ERROR,
        ]);
    }

    /**
     * Continue a job from outside (i.e. a controller)
     * after a $context->retainJob().
     */
    public function continueJob(string $workflowId, string $jobId, ?array $jobInputs = null): void
    {
        $this->rerunJobs($workflowId, $jobId, [
            JobState::STATUS_RUNNING,
        ], $jobInputs);
    }

    public function rerunJobs(string $workflowId, ?string $jobIdFilter = null, ?array $expectedStatuses = null, ?array $jobInputs = null): void
    {
        $this->wrapInTransaction(function () use ($workflowId, $jobIdFilter, $expectedStatuses, $jobInputs): void {
            $this->doRerunJobs($workflowId, $jobIdFilter, $expectedStatuses, $jobInputs);
        });

        $this->flush();
    }

    private function doRerunJobs(string $workflowId, ?string $jobIdFilter, ?array $expectedStatuses, ?array $jobInputs): void
    {
        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->acquireWorkflowLock($workflowId);
        }

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

                $jobState = $this->stateRepository->getLastJobState($workflowId, $jobId);
                if (null !== $jobState && (null === $expectedStatuses || in_array($jobState->getStatus(), $expectedStatuses, true))) {
                    if (!$run->getJob()->isDisabled()) {
                        $jobsToTrigger[] = $jobId;
                    }
                }

                if (null !== $jobIdFilter) {
                    break 2;
                }
            }
        }

        if (!empty($jobsToTrigger)) {
            $workflowState->setStatus(WorkflowState::STATUS_STARTED);
            $this->stateRepository->persistWorkflowState($workflowState);

            $jobInputs['rerun'] = true;
            foreach ($jobsToTrigger as $jobId) {
                $this->triggerJob($workflowState, $jobId, $jobInputs);
            }
        }

        if ($this->stateRepository instanceof LockAwareStateRepositoryInterface) {
            $this->stateRepository->releaseWorkflowLock($workflowId);
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

                if ($job->isDisabled()) {
                    continue;
                }

                $jobState = $this->stateRepository->getLastJobState($state->getId(), $jobId);
                if (null === $jobState) {
                    if ($this->satisfiesAllNeeds($statuses, $job)) {
                        return [$jobId, null];
                    } else {
                        continue;
                    }
                }

                $statuses[$jobId] = $jobState->getStatus();

                if (JobState::STATUS_FAILURE === $jobState->getStatus()) {
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
            if (!isset($states[$need]) || JobState::STATUS_SUCCESS !== $states[$need]) {
                return false;
            }
        }

        return true;
    }

    private function triggerJob(WorkflowState $workflowState, string $jobId, ?array $jobInputs = null): void
    {
        $workflowId = $workflowState->getId();

        $jobState = $this->stateRepository->createJobState($workflowId, $jobId);
        if (null !== $jobInputs) {
            $inputs = $jobState->getInputs() ?? new Inputs();
            $jobState->setInputs($inputs->mergeWith($jobInputs));
        }
        $this->stateRepository->persistJobState($jobState);

        $jobTrigger = new JobTrigger($workflowId, $jobId, $jobState->getId());
        if ($this->trigger->isSynchronous()) {
            $this->trigger->triggerJob($jobTrigger);
        } else {
            $this->workflowsToTrigger[] = $jobTrigger;
        }
    }

    private function flush(): void
    {
        $jobsToTrigger = $this->workflowsToTrigger;
        $this->workflowsToTrigger = [];
        foreach ($jobsToTrigger as $jobTrigger) {
            $this->trigger->triggerJob($jobTrigger);
        }
    }
}
