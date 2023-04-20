<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Doctrine\Entity;

use Alchemy\Workflow\State\Inputs;
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
        $this->startedAt = $state->getStartedAt()->getDateTimeObject();
        $this->endedAt = $state->getEndedAt()?->getDateTimeObject();
    }

    public function getWorkflowState(): ModelWorkflowState
    {
        if (null === $this->workflowState) {
            try {
                $this->workflowState = unserialize($this->getState());
            } catch (\Throwable $e) {
                throw new \Exception(sprintf('Cannot read state: %s', $this->getState()), 0, $e);
            }
        }

        return $this->workflowState;
    }

    public function getDuration(): ?float
    {
        try {
            return $this->getWorkflowState()->getDuration();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getEventName(): ?string
    {
        try {
            return $this->getWorkflowState()->getEvent()?->getName();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getEventInputs(): ?Inputs
    {
        try {
            return $this->getWorkflowState()->getEvent()?->getInputs();
        } catch (\Exception $e) {
            return null;
        }
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
