<?php

namespace Alchemy\Workflow\Message;

final readonly class JobConsumer
{
    public function __construct(private string $workflowId, private string $jobId)
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
}
