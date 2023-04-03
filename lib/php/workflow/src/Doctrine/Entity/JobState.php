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
     * @ORM\Column(type="date_immutable", nullable=false)
     */
    protected ?\DateTimeImmutable $createdAt = null;

    protected ?ModelJobState $jobState = null;

    public function __construct(WorkflowState $workflow, string $jobId)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->workflow = $workflow;
        $this->jobId = $jobId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getJobState(): ModelJobState
    {
        if (null === $this->jobState) {
            $this->jobState = unserialize($this->getState());
        }

        return $this->jobState;
    }

    public function getWorkflow(): ?WorkflowState
    {
        return $this->workflow;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
