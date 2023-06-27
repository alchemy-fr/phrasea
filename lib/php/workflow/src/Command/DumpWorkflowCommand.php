<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Command;

use Alchemy\Workflow\Dumper\ConsoleWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpWorkflowCommand extends Command
{
    public function __construct(
        private readonly StateRepositoryInterface $stateRepository,
        private readonly WorkflowRepositoryInterface $workflowRepository
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('id', InputArgument::REQUIRED, 'The workflow ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = null;
        $workflowState = $this->stateRepository->getWorkflowState($input->getArgument('id'));

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
