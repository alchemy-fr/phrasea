<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use Alchemy\ESBundle\Service\IndexRemover;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ESRemoveIndexCommand extends Command
{
    public function __construct(
        private readonly IndexRemover $indexRemover,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('alchemy:es:delete-index')
            ->addOption('index', 'i', InputOption::VALUE_REQUIRED)
            ->addOption('remove-olds')
            ->addOption('olds-only')
            ->addOption('force-prefix', null, InputOption::VALUE_NONE, 'Force the prefix to be used for the index name')
            ->setDescription('Remove index and its aliases');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexRemover->removeIndices(
            $input->getOption('index'),
            oldsOnly: true === $input->getOption('olds-only'),
            removeOlds: true === $input->getOption('remove-olds'),
            forcePrefix: true === $input->getOption('force-prefix'),
            output: $output
        );

        return Command::SUCCESS;
    }
}
