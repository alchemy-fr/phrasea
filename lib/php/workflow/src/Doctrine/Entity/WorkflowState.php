<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

use Alchemy\Workflow\State\StateUtil;
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

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected string $name;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     */
    protected int $status;

    protected ?ModelWorkflowState $workflowState = null;

    /**
     * @ORM\OneToMany(targetEntity=JobState::class, mappedBy="wokflow", cascade={"remove"})
     */
    protected ?Collection $jobs = null;

    /**
     * @ORM\Column(type="date_immutable", nullable=false)
     */
    protected \DateTimeImmutable $startedAt;

    /**
     * @ORM\Column(type="date_immutable", nullable=true)
     */
    protected ?\DateTimeImmutable $endedAt = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->jobs = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(ModelWorkflowState $state): void
    {
        $this->state = serialize($state);
        $this->name = $state->getWorkflowName();
        $this->status = $state->getStatus();
        $this->startedAt = $state->getStartedAt();
        $this->endedAt = $state->getEndedAt();
    }

    public function getWorkflowState(): ModelWorkflowState
    {
        if (null === $this->workflowState) {
            $this->workflowState = unserialize($this->getState());
        }

        return $this->workflowState;
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

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
