<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

final class Workflow
{
    private string $name;

    private EnvVars $env;
    private JobList $jobs;
    private OnEventList $on;

    public function __construct(string $name)
    {
        $this->name = $name;
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
}
