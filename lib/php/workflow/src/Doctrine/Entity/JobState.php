<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

use Alchemy\Workflow\State\JobState as ModelJobState;
use Alchemy\Workflow\State\StateUtil;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

class JobState
{
    protected ?string $state = null;

    protected int $number;

    protected int $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    protected \DateTimeImmutable $triggeredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $endedAt = null;

    protected ?ModelJobState $jobState = null;

    public function __construct(
        protected readonly string $id,
        protected ?WorkflowState $workflow,
        protected string $jobId,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getDuration(): ?float
    {
        return $this->getJobState()->getDuration();
    }

    public function getDurationString(): string
    {
        return StateUtil::getFormattedDuration($this->getDuration());
    }

    public function setState(ModelJobState $state, EntityManagerInterface $em): void
    {
        $this->state = serialize($state);
        $this->triggeredAt = $state->getTriggeredAt()->getDateTimeObject();
        $this->endedAt = $state->getEndedAt()?->getDateTimeObject();
        $this->startedAt = $state->getStartedAt()?->getDateTimeObject();
        $this->status = $state->getStatus();
        $this->number = $state->getNumber();
    }

    public function getJobState(): ModelJobState
    {
        if (null === $this->jobState) {
            $this->jobState = unserialize($this->getState());
        }

        return $this->jobState;
    }

    public function getOutputs(): array
    {
        return $this->getJobState()->getOutputs()->getArrayCopy();
    }

    public function getInputs(): array
    {
        return $this->getJobState()->getInputs()->getArrayCopy();
    }

    public function getErrors(): array
    {
        return $this->getJobState()->getErrors();
    }

    public function getWorkflow(): ?WorkflowState
    {
        return $this->workflow;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getTriggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getNumber(): int
    {
        return $this->number;
    }
}
