<?php

namespace Alchemy\Workflow\Trigger;

final readonly class JobTrigger
{
    public function __construct(
        private string $workflowId,
        private string $jobId,
        private string $jobStateId,
        private ?string $queue = null,
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

    /**
     * Name of the Messenger transport the job is triggered on; null for the default routing.
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }
}
