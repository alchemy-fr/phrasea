<?php

declare(strict_types=1);

namespace App\Controller\Workflow;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Workflow\WorkflowState;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class CancelWorkflowAction
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private GetWorkflowAction $getAction,
    ) {
    }

    public function __invoke(WorkflowState $data): JsonResponse
    {
        $this->workflowOrchestrator->cancelWorkflow($data->getId());

        $controller = $this->getAction;

        return $controller($data);
    }
}
