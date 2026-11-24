<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\WorkflowState;

interface JobQueueResolverInterface
{
    /**
     * @return string|null the Messenger transport the job is triggered on, null for the default routing
     */
    public function resolveQueue(WorkflowState $state, Job $job): ?string;
}
