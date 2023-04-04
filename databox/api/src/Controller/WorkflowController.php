<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\Workflow\Dumper\JsonWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class WorkflowController
{
    private StateRepositoryInterface $stateRepository;
    private WorkflowRepositoryInterface $workflowRepository;

    public function __construct(
        StateRepositoryInterface $stateRepository,
        WorkflowRepositoryInterface $workflowRepository
    )
    {
        $this->stateRepository = $stateRepository;
        $this->workflowRepository = $workflowRepository;
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
}
