<?php

declare(strict_types=1);

namespace Alchemy\Workflow;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Executor\WorkflowExecutionContext;
use Alchemy\Workflow\Model\WorkflowList;
use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Runner\RunnerInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
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

        $this->stateRepository->persistWorkflowState($workflowState);

        $this->continueWorkflow($workflowState->getId(), $workflowState, $output);

        return $workflowState;
    }

    public function continueWorkflow(string $workflowId, ?WorkflowState $workflowState = null, OutputInterface $output = null): void
    {
        $output ??= new ConsoleOutput();

        if (null === $workflowState) {
            $workflowState = $this->stateRepository->getWorkflowState($workflowId);
        }

        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflows->getByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $nextJobId = $this->getNextJob($plan, $workflowState);
        do {
            $continue = $this->runJob($workflowState, $plan, $output, $nextJobId);

            if ($continue) {
                if (null === $nextJobId = $this->getNextJob($plan, $workflowState)) {
                    $continue = false;
                }
            }
        } while ($continue);

        $this->stateRepository->persistWorkflowState($workflowState);
    }

    private function getNextJob(Plan $plan, WorkflowState $state): ?string
    {
        $resultList = $this->stateRepository->getJobResultList($state->getId());

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

    private function runJob(WorkflowState $state, Plan $plan, OutputInterface $output, string $jobId): bool
    {
        $continue = false;

        $workflowContext = new WorkflowExecutionContext(
            $state,
            $plan,
            $this->stateRepository,
            $output,
            function () use (&$continue): void {
                $continue = true;
            }
        );

        $job = $plan->getJob($jobId);

        if (null !== $job->getIf()) {
            $workflowContext->setJobSkipped($jobId);

            return true;
        }

        $workflowContext->setJobTriggered($jobId);

        $this->runner->run($workflowContext, $jobId);

        return $continue;
    }

    public function setStateRepository(StateRepositoryInterface $stateRepository): void
    {
        $this->stateRepository = $stateRepository;
    }
}
