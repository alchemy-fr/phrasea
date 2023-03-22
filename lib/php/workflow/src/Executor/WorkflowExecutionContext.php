<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Exception\ConcurrencyException;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

class WorkflowExecutionContext
{
    private WorkflowState $state;
    private Plan $plan;
    private OutputInterface $output;
    private $next;
    private StateRepositoryInterface $stateRepository;

    public function __construct(
        WorkflowState $state,
        Plan $plan,
        StateRepositoryInterface $stateRepository,
        OutputInterface $output,
        callable $next
    )
    {
        $this->state = $state;
        $this->plan = $plan;
        $this->output = $output;
        $this->next = $next;
        $this->stateRepository = $stateRepository;
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
        $this->createJobState($jobId, JobState::STATUS_TRIGGERED);
    }

    public function acquireJobTriggerLock(string $jobId, callable $callback): void
    {
        $this->stateRepository->getJobState($this->state->getId(), $jobId);

        try {
            $callback();
        } catch (\Throwable $e) {
        }

        $this->createJobState($jobId, JobState::STATUS_TRIGGERED);
    }

    public function setJobSkipped(string $jobId): void
    {
        $this->createJobState($jobId, JobState::STATUS_SKIPPED);
    }

    private function createJobState(string $jobId, int $status): void
    {
        $workflowId = $this->getState()->getId();
        if (null !== $this->stateRepository->getJobState($workflowId, $jobId)) {
            throw new ConcurrencyException(sprintf('State for job "%s" already exists', $jobId));
        }

        $jobState = new JobState($workflowId, $jobId, $status);

        $this->stateRepository->persistJobState($jobState);
    }

    public function updateJobState(string $jobId, int $status, ?array $outputs = null): void
    {
        $jobState = $this->stateRepository->getJobState($this->state->getId(), $jobId);
        $jobState->setStatus($status);
        if (null !== $outputs) {
            $jobState->setOutputs($outputs);
        }

        $this->stateRepository->persistJobState($jobState);
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
