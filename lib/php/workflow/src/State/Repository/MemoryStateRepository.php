<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class MemoryStateRepository implements StateRepositoryInterface
{
    /**
     * @var array<string, JobState>
     */
    protected array $statuses = [];

    /**
     * @var array<string, array<string, JobState[]>>
     */
    protected array $statusesByJobId = [];

    /**
     * @var array<string, WorkflowState>
     */
    private array $workflows = [];

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
        $this->statusesByJobId[$id] ??= [];
    }

    public function getJobState(string $workflowId, string $jobStateId): ?JobState
    {
        $this->ensureWorkflowExists($workflowId);

        return $this->statuses[$jobStateId] ?? null;
    }

    public function getLastJobState(string $workflowId, string $jobId): ?JobState
    {
        $this->ensureWorkflowExists($workflowId);

        $states = $this->statusesByJobId[$workflowId][$jobId] ?? [];

        return end($states) ?: null;
    }

    public function createJobState(string $workflowId, string $jobId): JobState
    {
        $number = count($this->statusesByJobId[$workflowId][$jobId] ?? []);

        $state = new JobState(
            $workflowId,
            $jobId,
            id: sprintf('%s-%d', $jobId, $number),
            number: $number,
        );

        $this->persistJobState($state);

        return $state;
    }

    public function getJobStates(string $workflowId, string $jobId): array
    {
        $this->ensureWorkflowExists($workflowId);

        return $this->statusesByJobId[$workflowId][$jobId] ?? [];
    }

    public function persistJobState(JobState $state): void
    {
        $workflowId = $state->getWorkflowId();
        $this->ensureWorkflowExists($workflowId);

        $exists = isset($this->statuses[$state->getId()]);
        $this->statuses[$state->getId()] = $state;
        $this->statusesByJobId[$workflowId][$state->getJobId()] ??= [];

        if ($exists) {
            $this->statusesByJobId[$workflowId][$state->getJobId()] = array_map(
                function (object $s) use ($state, &$found): object {
                    if ($s->getId() === $state->getId()) {
                        $found = true;

                        return $state;
                    } else {
                        return $s;
                    }
                },
                $this->statusesByJobId[$workflowId][$state->getJobId()]
            );
        } else {
            $this->statusesByJobId[$workflowId][$state->getJobId()][] = $state;
        }
    }

    public function removeJobState(string $workflowId, string $jobStateId): void
    {
        unset($this->statuses[$jobStateId]);

        foreach ($this->statusesByJobId[$workflowId] as $jobId => $states) {
            $this->statusesByJobId[$workflowId][$jobId] = \array_filter(
                $states,
                fn (object $s): bool => $s->getId() !== $jobStateId
            );
        }
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
        foreach ($this->statusesByJobId[$workflowId][$jobId] ?? [] as $jobState) {
            unset($this->statuses[$jobState->getId()]);
        }

        $this->statusesByJobId[$workflowId][$jobId] = [];
    }

    private function ensureWorkflowExists(string $workflowId): void
    {
        if (!isset($this->statusesByJobId[$workflowId])) {
            throw new \LogicException(sprintf('Job container for workflow "%s" was not created. Please ensure the WorkflowState is persisted before.', $workflowId));
        }
    }
}
