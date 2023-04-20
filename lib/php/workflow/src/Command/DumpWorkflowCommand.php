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
    private StateRepositoryInterface $stateRepository;
    private WorkflowRepositoryInterface $workflowRepository;

    public function __construct(
        StateRepositoryInterface $stateRepository,
        WorkflowRepositoryInterface $workflowRepository
    )
    {
        parent::__construct();
        $this->stateRepository = $stateRepository;
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addArgument('id', InputArgument::REQUIRED, 'The workflow ID');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workflowState = $this->stateRepository->getWorkflowState($input->getArgument('id'));

        $event = $workflowState->getEvent();
        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $dumper = new ConsoleWorkflowDumper();
        $dumper->dumpWorkflow($workflowState, $plan, $output);

        return 0;
    }
}
