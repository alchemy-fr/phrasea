<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Ramsey\Uuid\Uuid;

class WorkflowState
{
    final public const STATUS_STARTED = 0;
    final public const STATUS_SUCCESS = 1;
    final public const STATUS_FAILURE = 2;
    final public const STATUS_CANCELLED = 3;

    private string $id;
    private Context $context;
    private ?MicroDateTime $startedAt = null;
    private ?MicroDateTime $endedAt = null;
    private ?MicroDateTime $cancelledAt = null;
    private int $status = self::STATUS_STARTED;

    public function __construct(
        private StateRepositoryInterface $stateRepository,
        private string $workflowName,
        private ?WorkflowEvent $event,
        ?string $id = null,
        array $context = []
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->startedAt = new MicroDateTime();
        $this->context = new Context($context);
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

    public function getStartedAt(): MicroDateTime
    {
        return $this->startedAt;
    }

    public function getJobState(string $jobId): ?JobState
    {
        return $this->stateRepository->getJobState($this->id, $jobId);
    }

    public function getDuration(): ?float
    {
        return $this->endedAt?->getDiff($this->startedAt);
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
            'context' => $this->context,
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
        $this->context = $data['context'];
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

    public function getEndedAt(): ?MicroDateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(MicroDateTime $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getCancelledAt(): ?MicroDateTime
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(MicroDateTime $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->status;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
