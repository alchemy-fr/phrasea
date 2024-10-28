<?php

namespace Alchemy\Workflow\Listener;

readonly class JobUpdateEvent
{
    public function __construct(
        private string $workflowId,
        private string $jobId,
        private string $jobStateId,
        private int $status,
    ) {
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getJobStateId(): string
    {
        return $this->jobStateId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
