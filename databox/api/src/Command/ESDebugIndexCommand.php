<?php

declare(strict_types=1);

namespace App\Command;

use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function sprintf;

class ESDebugIndexCommand extends Command
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
            ->setName('app:es:debug-index')
            ->addOption('index', 'i', InputOption::VALUE_REQUIRED)
            ->setDescription('Display index settings & mapping');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexArg = $input->getOption('index');
        $indices = null === $indexArg ? array_keys($this->indexManager->getAllIndexes()) : [$indexArg];

        foreach ($indices as $i) {
            $output->writeln(sprintf('Index <comment>%s</comment>', $i));
            $index = $this->indexManager->getIndex($i);

            $indexName = $index->getName();

            $response = $this->client->request($indexName);
            $output->writeln(json_encode($response->getData(), JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }
}
