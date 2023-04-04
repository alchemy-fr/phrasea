<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Ramsey\Uuid\Uuid;

class WorkflowState
{
    public const STATUS_STARTED = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAILURE = 2;

    private string $id;
    private StateRepositoryInterface $stateRepository;
    private ?\DateTimeImmutable $startedAt = null;
    private ?\DateTimeImmutable $endedAt = null;
    private ?WorkflowEvent $event;
    private string $workflowName;
    private int $status = self::STATUS_STARTED;

    public function __construct(
        StateRepositoryInterface $stateRepository,
        string $workflowName,
        ?WorkflowEvent $event,
        ?string $id = null
    )
    {
        $this->stateRepository = $stateRepository;
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->startedAt = new \DateTimeImmutable();
        $this->event = $event;
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

    public function getDuration(): ?int
    {
        if (null !== $this->endedAt) {
            return $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return null;
    }

    public function getDurationString(): string
    {
        return StateUtil::getFormattedDuration($this->getDuration());
    }

    public function __serialize(): array
    {
        return [
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'event' => $this->event,
            'workflowName' => $this->workflowName,
            'id' => $this->id,
            'status' => $this->status,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->startedAt = $data['startedAt'];
        $this->event = $data['event'];
        $this->workflowName = $data['workflowName'];
        $this->id = $data['id'];
        $this->status = $data['status'];
        $this->endedAt = $data['endedAt'];
    }

    public function setStateRepository(StateRepositoryInterface $stateRepository): void
    {
        $this->stateRepository = $stateRepository;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }
}
