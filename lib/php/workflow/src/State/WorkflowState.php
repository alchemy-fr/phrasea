<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Ramsey\Uuid\Uuid;

class WorkflowState
{
    private string $id;
    private StateRepositoryInterface $stateRepository;
    private \DateTimeImmutable $startedAt;
    private ?WorkflowEvent $event;
    private string $workflowName;

    public function __construct(
        StateRepositoryInterface $stateRepository,
        string $workflowName,
        ?WorkflowEvent $event,
        ?string $id = null
    )
    {
        $this->stateRepository = $stateRepository;
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

    public function getJobState(string $jobId): ?JobState
    {
        return $this->stateRepository->getJobState($this->id, $jobId);
    }

    public function __serialize(): array
    {
        return [
            'startedAt' => $this->startedAt,
            'event' => $this->event,
            'workflowName' => $this->workflowName,
            'id' => $this->id,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->startedAt = $data['startedAt'];
        $this->event = $data['event'];
        $this->workflowName = $data['workflowName'];
        $this->id = $data['id'];
    }

    public function setStateRepository(StateRepositoryInterface $stateRepository): void
    {
        $this->stateRepository = $stateRepository;
    }
}
