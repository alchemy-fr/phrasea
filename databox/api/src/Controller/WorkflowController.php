<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\Workflow\Dumper\JsonWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class WorkflowController
{
    public function __construct(
        private readonly StateRepositoryInterface $stateRepository,
        private readonly WorkflowRepositoryInterface $workflowRepository,
        private readonly WorkflowOrchestrator $workflowOrchestrator,
    )
    {
    }

    /**
     * @Route("/workflows/{id}")
     */
    public function getWorkflowAction(string $id): JsonResponse
    {
        $dumper = new JsonWorkflowDumper();

        $workflowState = $this->stateRepository->getWorkflowState($id);

        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $output = new BufferedOutput();
        $event = $workflowState->getEvent();
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $dumper->dumpWorkflow($workflowState, $plan, $output);

        return new JsonResponse($output->fetch(), 200, [], true);
    }

    /**
     * @Route("/workflows/{id}/jobs/{jobId}/rerun", methods={"POST"})
     */
    public function rerunJob(string $id, string $jobId): JsonResponse
    {
        $this->workflowOrchestrator->rerunJobs($id, $jobId);

        return $this->getWorkflowAction($id);
    }
}
