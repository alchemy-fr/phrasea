<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class Job
{
    private string $name;

    private readonly NeedList $needs;

    private readonly EnvVars $env;
    private ?string $if = null;

    private readonly StepList $steps;
    private With $with;
    private array $outputs = [];
    private ?string $result = null;
    private bool $continueOnError = false;

    public function __construct(private readonly string $id)
    {
        $this->env = new EnvVars();
        $this->steps = new StepList();
        $this->needs = new NeedList();
        $this->with = new With();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getNeeds(): NeedList
    {
        return $this->needs;
    }

    public function getEnv(): EnvVars
    {
        return $this->env;
    }

    /**
     * @return Step[]
     */
    public function getSteps(): StepList
    {
        return $this->steps;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function isContinueOnError(): bool
    {
        return $this->continueOnError;
    }

    public function setContinueOnError(bool $continueOnError): void
    {
        $this->continueOnError = $continueOnError;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    public function setOutputs(array $outputs): void
    {
        $this->outputs = $outputs;
    }

    public function getWith(): With
    {
        return $this->with;
    }

    public function setWith(With $with): void
    {
        $this->with = $with;
    }

    public function getName(): string
    {
        return $this->name ?? $this->id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
