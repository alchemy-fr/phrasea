<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

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

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getWorkflow(): ?WorkflowState
    {
        return $this->workflow;
    }
}
