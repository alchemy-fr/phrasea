<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\Mapping\IndexSyncState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ESMappingValidateCommand extends Command
{
    public function __construct(
        private readonly IndexSyncState $indexSyncState,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:search:validate')
            ->setDescription('Check whether Elasticsearch documents should be re-indexed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $returnCode = Command::SUCCESS;
        foreach ([
            'collection',
            'asset',
        ] as $indexName) {
            $shouldReindex = $this->indexSyncState->shouldReindex($indexName);

            if (null === $shouldReindex) {
                $output->writeln(sprintf('<fg=yellow>[WARNING]</> There is no current sync state in database for index <comment>%s</comment>. You should run your first populate to get synced!', $indexName));
                $returnCode = 2;
            } elseif (true === $shouldReindex) {
                $output->writeln(sprintf('<fg=red>[KO]</> Index <comment>%s</comment> should be re-populated.', $indexName));
                $returnCode = 1;
            } else {
                $output->writeln(sprintf('<fg=green>[OK]</> Index <comment>%s</comment> is synced.', $indexName));
            }
        }

        return $returnCode;
    }
}
