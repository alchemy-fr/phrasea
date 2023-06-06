<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Alchemy\Workflow\State\WorkflowState;

readonly class JobsAccessor
{
    public function __construct(private WorkflowState $workflowState)
    {
    }

    public function __get(string $name)
    {
        if (null !== $jobState = $this->workflowState->getJobState($name)) {
            return new ObjectOrArrayAccessor($jobState);
        }

        throw new \InvalidArgumentException(sprintf('Job "%s" does not exist or has no state', $name));
    }
}
