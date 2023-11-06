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
    public function __construct(private readonly Workflow $workflow, private readonly Job $job)
    {
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
