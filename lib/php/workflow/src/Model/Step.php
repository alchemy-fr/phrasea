<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class Step
{
    private readonly EnvVars $env;
    private string $executor = 'bash';
    private ?string $run = null;
    private ?string $uses = null;
    private array $with = [];
    private bool $continueOnError = false;

    public function __construct(private readonly string $id, private readonly ?string $name)
    {
        $this->env = new EnvVars();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEnv(): EnvVars
    {
        return $this->env;
    }

    public function getExecutor(): string
    {
        return $this->executor;
    }

    public function getRun(): ?string
    {
        return $this->run;
    }

    public function setExecutor(string $executor): void
    {
        $this->executor = $executor;
    }

    public function setRun(?string $run): void
    {
        $this->run = $run;
    }

    public function isContinueOnError(): bool
    {
        return $this->continueOnError;
    }

    public function setContinueOnError(bool $continueOnError): void
    {
        $this->continueOnError = $continueOnError;
    }

    public function getUses(): ?string
    {
        return $this->uses;
    }

    public function setUses(?string $uses): void
    {
        $this->uses = $uses;
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function setWith(array $with): void
    {
        $this->with = $with;
    }

    public function __clone(): void
    {
        $this->env = clone $this->env;
    }
}
