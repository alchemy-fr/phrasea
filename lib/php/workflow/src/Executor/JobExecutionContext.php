<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Symfony\Component\Console\Output\OutputInterface;

class JobExecutionContext
{
    private WorkflowExecutionContext $workflowContext;

    public function __construct(WorkflowExecutionContext $workflowContext)
    {
        $this->workflowContext = $workflowContext;
    }

    public function getOutput(): OutputInterface
    {
        return $this->workflowContext->getOutput();
    }
}
