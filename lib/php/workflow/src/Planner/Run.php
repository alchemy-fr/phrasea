<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;

/**
 * Run represents a job from a workflow that needs to be run.
 */
class Run
{
    private Workflow $workflow;
    private Job $job;

    public function __construct(Workflow $workflow, Job $job)
    {
        $this->workflow = $workflow;
        $this->job = $job;
    }

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function getJob(): Job
    {
        return $this->job;
    }
}
