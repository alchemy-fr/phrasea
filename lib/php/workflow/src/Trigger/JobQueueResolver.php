<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\WorkflowState;

final readonly class JobQueueResolver implements JobQueueResolverInterface
{
    public function resolveQueue(WorkflowState $state, Job $job): ?string
    {
        return $job->getQueue();
    }
}
