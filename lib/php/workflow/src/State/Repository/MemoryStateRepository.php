<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class MemoryStateRepository implements StateRepositoryInterface
{
    /**
     * @var array<string, WorkflowState>
     */
    private array $workflows = [];

    /**
     * @var array<string, JobState[]>
     */
    private array $jobs = [];

    public function getWorkflowState(string $id): WorkflowState
    {
        if (!isset($this->workflows[$id])) {
            throw new \InvalidArgumentException(sprintf('Workflow state "%s" does not exist', $id));
        }

        return $this->workflows[$id];
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $id = $state->getId();
        $this->workflows[$id] = $state;
        $this->jobs[$id] ??= [];
    }

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $this->ensureWorkflowExists($workflowId);

        return $this->jobs[$workflowId][$jobId] ?? null;
    }

    public function persistJobState(JobState $state): void
    {
        $workflowId = $state->getWorkflowId();
        $this->ensureWorkflowExists($workflowId);

        $this->jobs[$workflowId][$state->getJobId()] = $state;
    }

    public function removeJobState(string $workflowId, string $jobId): void
    {
        unset($this->jobs[$workflowId][$jobId]);
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
        $this->removeJobState($workflowId, $jobId);
    }

    private function ensureWorkflowExists(string $workflowId): void
    {
        if (!isset($this->jobs[$workflowId])) {
            throw new \LogicException(sprintf('Job container for workflow "%s" was not created. Please ensure the WorkflowState is persisted before.', $workflowId));
        }
    }
}
