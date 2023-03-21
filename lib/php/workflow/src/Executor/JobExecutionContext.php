<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

class JobExecutionContext
{
    private WorkflowExecutionContext $workflowContext;

    public function __construct(WorkflowExecutionContext $workflowContext)
    {
        $this->workflowContext = $workflowContext;
    }

    public function getWorkflowContext(): WorkflowExecutionContext
    {
        return $this->workflowContext;
    }
}
