<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Symfony\Component\Console\Output\OutputInterface;

class RunContext
{
    private array $inputs = [];
    private array $env = [];
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function setInput(string $key, $value): void
    {
        $this->inputs[$key] = $value;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function setEnv(string $key, $value): void
    {
        $this->env[$key] = $value;
    }

    public function getEnv(): array
    {
        return $this->env;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
