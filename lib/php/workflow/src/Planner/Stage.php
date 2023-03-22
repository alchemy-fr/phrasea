<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

/**
 * Stage contains a list of runs to execute in parallel.
 */
class Stage
{
    private RunList $runs;

    public function __construct()
    {
        $this->runs = new RunList();
    }

    /**
     * @return Run[]
     */
    public function getRuns(): RunList
    {
        return $this->runs;
    }

    public function containsJobId(string $jobId): bool
    {
        /* @var Stage $stage */
        foreach ($this->getRuns() as $run) {
            if ($run->getJob()->getId() === $jobId) {
                return true;
            }
        }

        return false;
    }
}
