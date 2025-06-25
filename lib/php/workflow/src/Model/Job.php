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
    private bool $disabled = false;
    private ?string $disabledReason = null;
    private array $metadata = [];

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

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function getDisabledReason(): ?string
    {
        return $this->disabledReason;
    }

    public function markDisabled(?string $reason): void
    {
        $this->disabled = true;
        $this->disabledReason = $reason;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function __clone(): void
    {
        $this->env = clone $this->env;
        $this->steps = clone $this->steps;
        $this->needs = clone $this->needs;
        $this->with = clone $this->with;
    }
}
