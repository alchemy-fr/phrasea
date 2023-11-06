<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Date\MicroDateTime;

class StepState
{
    private readonly Outputs $outputs;
    private ?MicroDateTime $startedAt = null;
    private ?MicroDateTime $endedAt = null;

    public function __construct(private readonly string $id)
    {
        $this->outputs = new Outputs();
    }

    public function getDuration(): ?float
    {
        return $this->endedAt?->getDiff($this->startedAt);
    }

    public function getStartedAt(): ?MicroDateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?MicroDateTime $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getEndedAt(): ?MicroDateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(?MicroDateTime $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOutputs(): Outputs
    {
        return $this->outputs;
    }
}
