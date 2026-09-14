<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugElasticSearchIndexCommand extends Command
{
    public function __construct(
        private readonly IndexManager $indexManager,
        private readonly Client $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('alchemy:es:debug-index')
            ->addOption('index', 'i', InputOption::VALUE_REQUIRED, 'Logical index name (e.g. "asset"); defaults to every configured index')
            ->setDescription('Display index settings & mapping')
            ->setHelp('Read-only: fetches and prints the settings, mappings and aliases of the physical index behind each logical FOS Elastica index.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indexArg = $input->getOption('index');
        $indices = null === $indexArg ? array_keys($this->indexManager->getAllIndexes()) : [$indexArg];

        $io->comment(sprintf(
            'Fetching settings, mappings and aliases of %s (read-only, nothing is modified).',
            null === $indexArg ? sprintf('all %d configured indices', count($indices)) : sprintf('index "%s"', $indexArg),
        ));

        foreach ($indices as $i) {
            $index = $this->indexManager->getIndex($i);
            $indexName = $index->getName();

            $io->section(sprintf('Index "%s" (physical name: %s)', $i, $indexName));

            $response = $this->client->indices()->get(['index' => $indexName]);
            $output->writeln(json_encode($response->asArray(), JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }
}
