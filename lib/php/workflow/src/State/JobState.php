<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class JobState
{
    const STATE_TRIGGERED = 0;
    const STATE_SUCCESS = 1;
    const STATE_FAILURE = 2;
    const STATE_SKIPPED = 3;
    private int $state;

    private ?array $outputs;

    public function __construct(int $state, ?array $outputs = null)
    {
        $this->state = $state;
        $this->outputs = $outputs;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function setState(int $state): void
    {
        $this->state = $state;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }
}
