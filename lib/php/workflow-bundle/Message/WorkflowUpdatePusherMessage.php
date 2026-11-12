<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Message;

/**
 * Real-time "workflow_update" notification, pushed to Sockudo/Pusher by
 * WorkflowUpdatePusherHandler. Route it to an async transport so that a slow or
 * unreachable Pusher host never blocks (or fails) the workflow itself.
 */
final readonly class WorkflowUpdatePusherMessage
{
    public function __construct(
        private string $workflowId,
        private int $status,
    ) {
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
