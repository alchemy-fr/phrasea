<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

final class Workflow
{
    private readonly EnvVars $env;
    private readonly JobList $jobs;
    private readonly OnEventList $on;

    public function __construct(private string $name)
    {
        $this->env = new EnvVars();
        $this->jobs = new JobList();
        $this->on = new OnEventList();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEnv(): EnvVars
    {
        return $this->env;
    }

    /**
     * @return Job[]
     */
    public function getJobs(): JobList
    {
        return $this->jobs;
    }

    public function getJob(string $id): Job
    {
        return $this->jobs->offsetGet($id);
    }

    /**
     * @return string[]
     */
    public function getJobIds(): array
    {
        return array_keys($this->jobs->getArrayCopy());
    }

    /**
     * @return OnEventList|OnEvent[]
     */
    public function getOn(): OnEventList
    {
        return $this->on;
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
    }

    public function __clone(): void
    {
        $this->env = clone $this->env;
        $this->jobs = clone $this->jobs;
        $this->on = clone $this->on;
    }
}
