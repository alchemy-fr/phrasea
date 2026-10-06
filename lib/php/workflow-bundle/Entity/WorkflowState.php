<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Entity;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\StateUtil;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workflow_state')]
class WorkflowState
{
    #[ORM\Column(type: Types::TEXT)]
    protected ?string $state = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $status;

    protected ?ModelWorkflowState $workflowState = null;

    protected ?Collection $jobs = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $endedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        protected string $id,
    ) {
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

    public function setState(ModelWorkflowState $state, EntityManagerInterface $em): void
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
        } catch (\Exception) {
            return null;
        }
    }

    public function getEventName(): ?string
    {
        try {
            return $this->getWorkflowState()->getEvent()?->getName();
        } catch (\Exception) {
            return null;
        }
    }

    public function getEventInputs(): ?Inputs
    {
        try {
            return $this->getWorkflowState()->getEvent()?->getInputs();
        } catch (\Exception) {
            return null;
        }
    }

    public function getContext(): ?array
    {
        try {
            return $this->getWorkflowState()->getContext()->getArrayCopy();
        } catch (\Exception) {
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
