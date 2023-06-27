<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

use Alchemy\Workflow\Model\Job;

/**
 * Plan contains a list of stages to run in series.
 */
class Plan
{
    public function __construct(private readonly StageList $stages)
    {
    }

    /**
     * @return Stage[]
     */
    public function getStages(): StageList
    {
        return $this->stages;
    }

    public function getJob($jobId): Job
    {
        foreach ($this->stages as $stage) {
            foreach ($stage->getRuns() as $run) {
                $job = $run->getJob();
                if ($job->getId() === $jobId) {
                    return $job;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('Job "%s" not found in plan', $jobId));
    }
}
