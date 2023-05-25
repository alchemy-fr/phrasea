<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\Outputs;
use Symfony\Component\Console\Output\OutputInterface;

class RunContext extends JobContext
{
    private bool $retainJob = false;

    public function __construct(
        OutputInterface $output,
        Inputs $inputs,
        EnvContainer $envs,
        private readonly Outputs $outputs,
    )
    {
        parent::__construct($output, $inputs, $envs);
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
}
