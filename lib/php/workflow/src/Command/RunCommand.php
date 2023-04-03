<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Command;

use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{

    private WorkflowOrchestrator $orchestrator;

    public function __construct(
        WorkflowOrchestrator $orchestrator
    )
    {
        parent::__construct();
        $this->orchestrator = $orchestrator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The workflow name to run');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->orchestrator->startWorkflow($input->getArgument('name'));

        return 0;
    }
}