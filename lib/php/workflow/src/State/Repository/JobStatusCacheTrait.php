<?php

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

trait JobStatusCacheTrait
{
    /**
     * @var array<string, WorkflowState>
     */
    protected array $workflows = [];

    /**
     * @var array<string, JobState>
     */
    protected array $statuses = [];

    /**
     * @var array<string, array<string, JobState[]>>
     */
    protected array $statusesByJobId = [];

    /**
     * @var array<string, array<string, JobState>>
     */
    protected array $lastByJobId = [];

    public function clearCache(): void
    {
        $this->statuses = [];
        $this->statusesByJobId = [];
        $this->workflows = [];
        $this->lastByJobId = [];
    }

    protected function cacheWorkflowState(string $workflowId, ?object $state): void
    {
        if (null === $state) {
            unset($this->workflows[$workflowId]);
        } else {
            $this->workflows[$workflowId] = $state;
        }
    }

    protected function cacheJobState(string $workflowId, string $jobStateId, ?object $state): void
    {
        if (null === $state) {
            $this->removeJobStateFromCache($workflowId, $jobStateId);
        } else {
            $this->statuses[$state->getId()] = $state;

            if (isset($this->lastByJobId[$workflowId])) {
                foreach ($this->lastByJobId[$workflowId] as $jobId => $state) {
                    if ($state->getId() === $jobStateId) {
                        $this->lastByJobId[$workflowId][$jobId] = $state;
                    }
                }
            }

            if (isset($this->statusesByJobId[$workflowId][$state->getJobId()])) {
                $this->statusesByJobId[$workflowId][$state->getJobId()] = array_map(
                    function (object $s) use ($state): object {
                        if ($s->getId() === $state->getId()) {
                            return $state;
                        }

                        return $s;

                    },
                    $this->statusesByJobId[$workflowId][$state->getJobId()]
                );
            }
        }
    }

    protected function cacheJobStates(string $workflowId, string $jobId, array $states): void
    {
        $this->statusesByJobId[$workflowId][$jobId] = $states;
        foreach ($states as $state) {
            $this->statuses[$state->getId()] = $state;
        }
    }

    protected function cacheLastJobState(string $workflowId, string $jobId, object $state): void
    {
        $this->lastByJobId[$workflowId][$jobId] = $state;
        $this->statuses[$state->getId()] = $state;
    }

    public function resetJobStateFromCache(string $workflowId, string $jobId): void
    {
        foreach ($this->statusesByJobId[$workflowId][$jobId] ?? [] as $jobState) {
            unset($this->statuses[$jobState->getId()]);
        }

        $this->statusesByJobId[$workflowId][$jobId] = [];
    }

    protected function removeJobStateFromCache(string $workflowId, string $jobStateId): void
    {
        unset($this->statuses[$jobStateId]);

        if (isset($this->statusesByJobId[$workflowId])) {
            foreach ($this->statusesByJobId[$workflowId] as $jobId => $states) {
                $this->statusesByJobId[$workflowId][$jobId] = array_filter(
                    $states,
                    fn (object $s): bool => $s->getId() !== $jobStateId
                );
            }
        }

        if (isset($this->lastByJobId[$workflowId])) {
            foreach ($this->lastByJobId[$workflowId] as $jobId => $state) {
                if ($state->getId() === $jobStateId) {
                    unset($this->lastByJobId[$workflowId][$jobId]);
                }
            }
        }
    }
}
