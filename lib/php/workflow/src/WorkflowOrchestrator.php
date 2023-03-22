<?php

declare(strict_types=1);

namespace Alchemy\Workflow;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Executor\WorkflowExecutionContext;
use Alchemy\Workflow\Model\WorkflowList;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Runner\RunnerInterface;
use Alchemy\Workflow\State\Provider\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class WorkflowOrchestrator
{
    private WorkflowList $workflows;
    private StateRepositoryInterface $stateRepository;
    private RunnerInterface $runner;

    public function __construct(array $workflows, StateRepositoryInterface $stateRepository, RunnerInterface $runner)
    {
        $this->workflows = new WorkflowList($workflows);
        $this->stateRepository = $stateRepository;
        $this->runner = $runner;
    }

    public function startWorkflow(string $workflowName, ?WorkflowEvent $event = null, OutputInterface $output = null): WorkflowState
    {
        $workflowState = new WorkflowState($workflowName, $event);

        $this->stateRepository->persistWorkflow($workflowState);

        $this->continueWorkflow($workflowState->getId(), $output);

        return $workflowState;
    }

    public function continueWorkflow(string $workflowId, OutputInterface $output = null): void
    {
        $output ??= new ConsoleOutput();
        $workflowState = $this->stateRepository->getWorkflow($workflowId);
        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflows->getByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $nextJobId = $this->getNextJob($plan, $workflowState);
        do {
            $continue = $this->runWorkflow($workflowState, $plan, $output, $nextJobId);
            $this->stateRepository->persistWorkflow($workflowState);

            if ($continue) {
                if (null === $nextJobId = $this->getNextJob($plan, $workflowState)) {
                    $continue = false;
                }
            }
        } while ($continue);
    }

    private function getNextJob(Plan $plan, WorkflowState $state): ?string
    {
        $resultList = $state->getJobResults();

        foreach ($plan->getStages() as $stage) {
            foreach ($stage->getRuns() as $run) {
                $jobId = $run->getJob()->getId();
                if (!$resultList->hasJobResult($jobId)) {
                    return $jobId;
                }
            }
        }

        return null;
    }

    private function runWorkflow(WorkflowState $workflowState, Plan $plan, OutputInterface $output, string $jobId): bool
    {
        $continue = false;

        $workflowContext = new WorkflowExecutionContext($workflowState, $plan, $output, function () use (&$continue): void {
            $continue = true;
        });

        $this->runner->run($workflowContext, $jobId);

        return $continue;
    }
}
