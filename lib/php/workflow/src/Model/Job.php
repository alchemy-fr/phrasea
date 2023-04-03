<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class Job
{
    private string $id;

    private NeedList $needs;

    private EnvVars $env;
    private ?string $if = null;

    private StepList $steps;
    private ?string $result = null;
    private bool $continueOnError = false;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->env = new EnvVars();
        $this->steps = new StepList();
        $this->needs = new NeedList();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNeeds(): NeedList
    {
        return $this->needs;
    }

    public function getEnv(): EnvVars
    {
        return $this->env;
    }

    public function getIf(): ?string
    {
        return $this->if;
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
}
