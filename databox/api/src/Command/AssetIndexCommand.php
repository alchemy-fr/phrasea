<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\AssetIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AssetIndexCommand extends Command
{
    public function __construct(
        private readonly AssetIndexer $assetIndexer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:es:index-assets')
            ->addOption('asset-id', null, InputOption::VALUE_REQUIRED, 'Only index a single asset by id')
            ->addOption('workspace-id', null, InputOption::VALUE_REQUIRED, 'Only index assets from a workspace')
            ->setDescription('Fast asset and attributes indexer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->assetIndexer->index(
            $output,
            $input->getOption('asset-id'),
            $input->getOption('workspace-id'),
        );

        return Command::SUCCESS;
    }
}
