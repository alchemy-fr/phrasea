<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Outputs;
use Symfony\Component\Console\Output\OutputInterface;

class RunContext extends JobContext
{
    private bool $retainJob = false;

    public function __construct(
        JobState $jobState,
        OutputInterface $output,
        Inputs $inputs,
        EnvContainer $envs,
        private readonly Outputs $outputs,
        private ?int $endStatus = null,
    ) {
        parent::__construct($jobState, $output, $inputs, $envs);
    }

    public function setOutput(string $key, $value): void
    {
        $this->outputs->set($key, $value);
    }

    public function isRetainJob(): bool
    {
        return $this->retainJob;
    }

    public function retainJob(bool $retainJob = true): void
    {
        $this->retainJob = $retainJob;
    }

    public function getEndStatus(): ?int
    {
        return $this->endStatus;
    }

    public function setEndStatus(int $endStatus): void
    {
        $this->endStatus = $endStatus;
    }
}
