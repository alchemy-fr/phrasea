<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Message;

/**
 * Real-time "job_update" notification, pushed to Soketi/Pusher by
 * JobUpdatePusherHandler. Route it to an async transport so that a slow or
 * unreachable Pusher host never blocks (or fails) the workflow job itself.
 */
final readonly class JobUpdatePusherMessage
{
    public function __construct(
        private string $workflowId,
        private string $jobId,
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

    public function getStatus(): int
    {
        return $this->status;
    }
}
