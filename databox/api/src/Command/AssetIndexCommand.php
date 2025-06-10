<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\AssetIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setDescription('Fast asset and attributes indexer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->assetIndexer->index($output);

        return Command::SUCCESS;
    }
}
