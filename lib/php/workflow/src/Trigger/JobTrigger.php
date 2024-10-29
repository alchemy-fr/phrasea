<?php

namespace Alchemy\Workflow\Trigger;

final readonly class JobTrigger
{
    public function __construct(
        private string $workflowId,
        private string $jobId,
        private string $jobStateId
    )
    {
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
}
