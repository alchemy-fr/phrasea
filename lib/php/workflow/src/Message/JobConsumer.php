<?php

namespace Alchemy\Workflow\Message;

final readonly class JobConsumer
{
    public function __construct(private string $workflowId, private string $jobStateId)
    {
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getJobStateId(): string
    {
        return $this->jobStateId;
    }
}
