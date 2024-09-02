<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:rendition-factory:execute')]
class ExecuteCommand extends Command
{
    public function __construct() {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('input', InputArgument::REQUIRED, 'Input file');
        $this->addOption('output', 'o', InputArgument::REQUIRED, 'Output file');
        $this->addOption('workspace_id', 'w', InputArgument::REQUIRED, 'Workaspace ID');
        $this->addOption('rendition', 'r', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Rendition name(s) [default: all renditions of the workspace]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFile = $input->getArgument('input');
        if(!file_exists($inputFile)) {
            $output->writeln("Input file not found: $inputFile");
            return 1;
        }



        return 0;
    }
}
