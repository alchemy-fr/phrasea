<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Action;

use Alchemy\Workflow\State\WorkflowState;

interface PostWorkflowActionInterface extends ActionInterface
{
    public function handlePostWorkflow(WorkflowState $workflowState): void;
}
