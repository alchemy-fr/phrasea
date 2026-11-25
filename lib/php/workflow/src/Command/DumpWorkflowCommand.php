<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Command;

use Alchemy\Workflow\Dumper\ConsoleWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:workflow:dump-workflow')]
class DumpWorkflowCommand extends Command
{
    public function __construct(
        private readonly StateRepositoryInterface $stateRepository,
        private readonly WorkflowRepositoryInterface $workflowRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The workflow ID')]
        string $id,
    ): int {
        $name = null;
        $workflowState = $this->stateRepository->getWorkflowState($id);

        $event = $workflowState->getEvent();
        $workflow = $this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName());
        if (null === $workflow) {
            throw new \RuntimeException(sprintf('Workflow "%s" not found', $name));
        }

        $planner = new WorkflowPlanner([$workflow]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $dumper = new ConsoleWorkflowDumper();
        $dumper->dumpWorkflow($workflowState, $plan, $output);

        return 0;
    }
}
