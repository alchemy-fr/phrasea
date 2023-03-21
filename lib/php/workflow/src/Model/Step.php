<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class Step
{
    private string $id;
    private ?string $name;

    private EnvVars $env;
    private ?string $if = null;
    private string $executor = 'bash';
    private ?string $run = null;

    public function __construct(string $id, ?string $name)
    {
        $this->id = $id;
        $this->name = $name;
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

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function getExecutor(): string
    {
        return $this->executor;
    }

    public function getRun(): ?string
    {
        return $this->run;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function setExecutor(string $executor): void
    {
        $this->executor = $executor;
    }

    public function setRun(?string $run): void
    {
        $this->run = $run;
    }
}
