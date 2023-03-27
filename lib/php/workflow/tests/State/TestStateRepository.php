<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\State\JobResultList;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;

class TestStateRepository implements StateRepositoryInterface
{
    private StateRepositoryInterface $inner;

    private array $logs = [];

    public function __construct(StateRepositoryInterface $inner)
    {
        $this->inner = $inner;
    }

    public function getWorkflowState(string $id): WorkflowState
    {
        $this->logs[] = ['getWorkflowState', $id];

        return $this->inner->getWorkflowState($id);
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $this->logs[] = ['persistWorkflowState', $state->getId()];

        $this->inner->persistWorkflowState($state);
    }

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $this->logs[] = ['getJobState', $workflowId, $jobId];

        return $this->inner->getJobState($workflowId, $jobId);
    }

    public function persistJobState(JobState $state): void
    {
        $this->logs[] = ['persistJobState', $state->getWorkflowId(), $state->getJobId(), $state->getStatus()];

        $this->inner->persistJobState($state);
    }

    public function getJobResultList(string $workflowId): JobResultList
    {
        $this->logs[] = ['getJobResultList', $workflowId];

        return $this->inner->getJobResultList($workflowId);
    }

    public function acquireJobLock(string $workflowId, string $jobId): void
    {
        $this->logs[] = ['acquireJobLock', $workflowId, $jobId];

        $this->inner->acquireJobLock($workflowId, $jobId);
    }

    public function releaseJobLock(string $workflowId, string $jobId): void
    {
        $this->logs[] = ['releaseJobLock', $workflowId, $jobId];

        $this->inner->releaseJobLock($workflowId, $jobId);
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
