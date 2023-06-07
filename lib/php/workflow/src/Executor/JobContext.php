<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Symfony\Component\Console\Output\OutputInterface;

class JobContext
{
    public function __construct(
        private readonly OutputInterface $output,
        private readonly Inputs $inputs,
        private readonly EnvContainer $envs,
    ) {
    }

    public function getInputs(): Inputs
    {
        return $this->inputs;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getEnvs(): EnvContainer
    {
        return $this->envs;
    }
}
