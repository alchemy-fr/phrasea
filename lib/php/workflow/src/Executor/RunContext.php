<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Symfony\Component\Console\Output\OutputInterface;

class RunContext
{
    private array $inputs;
    private array $outputs = [];
    private array $env;
    private OutputInterface $output;

    public function __construct(OutputInterface $output, array $inputs, array $env)
    {
        $this->output = $output;
        $this->inputs = $inputs;
        $this->env = $env;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function getEnv(string $key)
    {
        return $this->env[$key] ?? null;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    public function setOutput(string $key, $value): void
    {
        if (array_key_exists($key, $this->outputs)) {
            throw new \LogicException(sprintf('Output "%s" is already defined', $key));
        }

        $this->outputs[$key] = $value;
    }
}
