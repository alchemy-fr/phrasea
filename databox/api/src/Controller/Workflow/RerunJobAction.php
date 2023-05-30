<?php

declare(strict_types=1);

namespace App\Controller\Workflow;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Workflow\WorkflowState;

final readonly class RerunJobAction
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private GetWorkflowAction $getAction,
    )
    {
    }

    public function __invoke(WorkflowState $data, string $jobId)
    {
        $this->workflowOrchestrator->rerunJobs($data->getId(), $jobId);

        $controller = $this->getAction;

        return $controller($data);
    }
}
