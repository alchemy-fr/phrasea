<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Event\WorkflowEvent;
use Ramsey\Uuid\Uuid;

class WorkflowState
{
    private string $id;

    private \DateTimeImmutable $startedAt;
    private ?WorkflowEvent $event;
    private string $workflowName;

    public function __construct(string $workflowName, ?WorkflowEvent $event, ?string $id = null)
    {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->event = $event;
        $this->startedAt = new \DateTimeImmutable();
        $this->workflowName = $workflowName;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkflowName(): string
    {
        return $this->workflowName;
    }

    public function getEvent(): ?WorkflowEvent
    {
        return $this->event;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }
}
