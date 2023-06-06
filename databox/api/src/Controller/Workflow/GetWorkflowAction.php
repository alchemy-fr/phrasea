<?php

declare(strict_types=1);

namespace App\Controller\Workflow;

use Alchemy\Workflow\Dumper\JsonWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use App\Entity\Workflow\WorkflowState;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class GetWorkflowAction
{
    public function __construct(
        private StateRepositoryInterface $stateRepository,
        private WorkflowRepositoryInterface $workflowRepository,
    ) {
    }

    public function __invoke(WorkflowState $data): JsonResponse
    {
        $dumper = new JsonWorkflowDumper();

        $workflowState = $this->stateRepository->getWorkflowState($data->getId());

        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $output = new BufferedOutput();
        $event = $workflowState->getEvent();
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $dumper->dumpWorkflow($workflowState, $plan, $output);

        return new JsonResponse($output->fetch(), 200, [], true);
    }
}
