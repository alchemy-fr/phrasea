<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;

/**
 * @ORM\Entity()
 */
class WorkflowState
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=36, unique=true)
     */
    protected string $id;

    /**
     * @ORM\Column(type="text")
     */
    protected ?string $state = null;

    protected ?ModelWorkflowState $workflowState = null;

    /**
     * @ORM\OneToMany(targetEntity=JobState::class, mappedBy="wokflow", cascade={"remove"})
     */
    protected ?Collection $jobs = null;

    /**
     * @ORM\Column(type="date_immutable", nullable=false)
     */
    protected ?\DateTimeImmutable $createdAt = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->jobs = new ArrayCollection();
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

    public function getWorkflowState(): ModelWorkflowState
    {
        if (null === $this->workflowState) {
            $this->workflowState = unserialize($this->getState());
        }

        return $this->workflowState;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
