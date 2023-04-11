<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

use Alchemy\Workflow\State\JobState as ModelJobState;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

class JobState
{
    protected string $id;

    /**
     * @ORM\ManyToOne(targetEntity=WorkflowState::class, inversedBy="jobs")
     * @ORM\JoinColumn(name="workflow_id", nullable=false)
     */
    protected ?WorkflowState $workflow = null;

    /**
     * @ORM\Id
     * @ORM\Column(name="job_id", type="string", length=255)
     */
    protected string $jobId;

    /**
     * @ORM\Column(type="text")
     */
    protected ?string $state = null;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     */
    protected int $status;

    /**
     * @ORM\Column(type="date_immutable", nullable=false)
     */
    protected \DateTimeImmutable $triggeredAt;

    /**
     * @ORM\Column(type="date_immutable", nullable=true)
     */
    protected ?\DateTimeImmutable $startedAt = null;

    /**
     * @ORM\Column(type="date_immutable", nullable=true)
     */
    protected ?\DateTimeImmutable $endedAt = null;

    protected ?ModelJobState $jobState = null;

    public function __construct(WorkflowState $workflow, string $jobId)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->workflow = $workflow;
        $this->jobId = $jobId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(ModelJobState $state): void
    {
        $this->state = serialize($state);
        $this->triggeredAt = $state->getTriggeredAt();

        if ($state->getEndedAt()) {
            $this->endedAt = $state->getEndedAt();
        }
        if ($state->getStartedAt()) {
            $this->startedAt = $state->getStartedAt();
        }

        $this->status = $state->getStatus();
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
        return $this->getJobState()->getOutputs();
    }

    public function getError(): ?string
    {
        return $this->getJobState()->getError();
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
}
