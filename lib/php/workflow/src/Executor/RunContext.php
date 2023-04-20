<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\Outputs;
use Symfony\Component\Console\Output\OutputInterface;

readonly class RunContext
{
    public function __construct(
        private OutputInterface $output,
        private Inputs $inputs,
        private EnvContainer $envs,
        private Outputs $outputs,
    )
    {
    }

    public function getInputs(): Inputs
    {
        return $this->inputs;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function setOutput(string $key, $value): void
    {
        $this->outputs->set($key, $value);
    }

    public function getEnvs(): EnvContainer
    {
        return $this->envs;
    }
}
