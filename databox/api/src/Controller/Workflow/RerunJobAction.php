<?php

declare(strict_types=1);

namespace App\Controller\Workflow;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Workflow\WorkflowState;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class RerunJobAction
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private GetWorkflowAction $getAction,
    ) {
    }

    public function __invoke(WorkflowState $data, string $jobId): JsonResponse
    {
        $this->workflowOrchestrator->rerunJobs($data->getId(), $jobId);

        $controller = $this->getAction;

        return $controller($data);
    }
}
