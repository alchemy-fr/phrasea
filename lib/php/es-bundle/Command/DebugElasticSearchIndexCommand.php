<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:es:debug-index',
    description: 'Display index settings & mapping',
    help: 'Read-only: fetches and prints the settings, mappings and aliases of the physical index behind each logical FOS Elastica index.',
)]
class DebugElasticSearchIndexCommand extends Command
{
    public function __construct(
        private readonly IndexManager $indexManager,
        private readonly Client $client,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Option('Logical index name (e.g. "asset"); defaults to every configured index', shortcut: 'i')]
        ?string $index = null,
    ): int {
        $indices = null === $index ? array_keys($this->indexManager->getAllIndexes()) : [$index];

        $io->comment(sprintf(
            'Fetching settings, mappings and aliases of %s (read-only, nothing is modified).',
            null === $index ? sprintf('all %d configured indices', count($indices)) : sprintf('index "%s"', $index),
        ));

        foreach ($indices as $i) {
            $indexName = $this->indexManager->getIndex($i)->getName();

            $io->section(sprintf('Index "%s" (physical name: %s)', $i, $indexName));

            $response = $this->client->indices()->get(['index' => $indexName]);
            $output->writeln(json_encode($response->asArray(), JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }
}
