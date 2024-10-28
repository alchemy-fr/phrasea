<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\LockAwareStateRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;

class TestStateStateRepository implements LockAwareStateRepositoryInterface
{
    private array $logs = [];

    public function __construct(private readonly StateRepositoryInterface $inner)
    {
    }

    public function getWorkflowState(string $id): WorkflowState
    {
        $this->logs[] = ['getWorkflowState', $id];

        return $this->inner->getWorkflowState($id);
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $this->logs[] = ['persistWorkflowState', $state->getId(), $state->getStatus()];

        $this->inner->persistWorkflowState($state);
    }

    public function getJobState(string $workflowId, string $jobStateId): ?JobState
    {
        $this->logs[] = ['getJobState', $workflowId, $jobStateId];

        return $this->inner->getJobState($workflowId, $jobStateId);
    }

    public function getLastJobState(string $workflowId, string $jobId): ?JobState
    {
        $this->logs[] = ['getLastJobState', $workflowId, $jobId];

        return $this->inner->getLastJobState($workflowId, $jobId);
    }

    public function createJobState(string $workflowId, string $jobId): JobState
    {
        $this->logs[] = ['createJobState', $workflowId, $jobId];

        return $this->inner->createJobState($workflowId, $jobId);
    }

    public function getJobStates(string $workflowId, string $jobId): array
    {
        $this->logs[] = ['getJobState', $workflowId, $jobId];

        return $this->inner->getJobStates($workflowId, $jobId);
    }

    public function persistJobState(JobState $state): void
    {
        $this->logs[] = ['persistJobState', $state->getWorkflowId(), $state->getJobId(), $state->getStatus()];

        $this->inner->persistJobState($state);
    }

    public function removeJobState(string $workflowId, string $jobStateId): void
    {
        $this->logs[] = ['removeJobState', $workflowId, $jobStateId];

        $this->inner->removeJobState($workflowId, $jobStateId);
    }

    public function acquireJobLock(string $workflowId, string $jobStateId): void
    {
        $this->logs[] = ['acquireJobLock', $workflowId, $jobStateId];

        if ($this->inner instanceof LockAwareStateRepositoryInterface) {
            $this->inner->acquireJobLock($workflowId, $jobStateId);
        }
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
        $this->removeJobState($workflowId, $jobId);
    }

    public function releaseJobLock(string $workflowId, string $jobStateId): void
    {
        $this->logs[] = ['releaseJobLock', $workflowId, $jobStateId];

        if ($this->inner instanceof LockAwareStateRepositoryInterface) {
            $this->inner->releaseJobLock($workflowId, $jobStateId);
        }
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function flush(): void
    {
        $this->logs = [];
    }
}
