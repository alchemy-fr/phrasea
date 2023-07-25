<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Command;

use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:workflow:run')]
class RunCommand extends Command
{
    public function __construct(
        private readonly WorkflowOrchestrator $orchestrator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The workflow name to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->orchestrator->startWorkflow($input->getArgument('name'));

        return 0;
    }
}
