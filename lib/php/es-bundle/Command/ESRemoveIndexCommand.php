<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use Elastica\Exception\ExceptionInterface;
use Elastica\Request;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ESRemoveIndexCommand extends Command
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
            ->setName('alchemy:es:delete-index')
            ->addOption('index', 'i', InputOption::VALUE_REQUIRED)
            ->addOption('remove-olds')
            ->addOption('olds-only')
            ->addOption('force-prefix', null, InputOption::VALUE_NONE, 'Force the prefix to be used for the index name')
            ->setDescription('Remove index and its aliases');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexArg = $input->getOption('index');
        $forcePrefix = $input->getOption('force-prefix');
        $indices = null === $indexArg ? array_keys($this->indexManager->getAllIndexes()) : [$indexArg];

        foreach ($indices as $i) {
            $output->writeln(sprintf('Delete index <comment>%s</comment>', $i));
            $nbDeleted = 0;
            $index = $this->indexManager->getIndex($i);

            $oldsOnly = true === $input->getOption('olds-only');
            $removeOlds = $oldsOnly || true === $input->getOption('remove-olds');
            $indexName = $index->getName();

            $response = $this->client->request('_aliases');
            foreach ($response->getData() as $indexKey => $c) {
                if ($forcePrefix && str_starts_with($indexKey, $indexArg)) {
                    ++$nbDeleted;
                    $this->deleteIndex($indexKey);

                    continue;
                }

                if (isset($c['aliases'][$indexName])) {
                    if (!$oldsOnly) {
                        $output->writeln(sprintf('Removing aliased index <comment>%s</comment>', $indexKey));
                        ++$nbDeleted;
                        $this->deleteIndex($indexKey);
                    }

                    continue;
                }

                if ($removeOlds && 1 === preg_match('#^'.preg_quote($indexName).'_\d{4}-\d{2}-\d{2}-\d{6}$#', $indexKey)) {
                    $output->writeln(sprintf('Removing old index <comment>%s</comment>', $indexKey));
                    ++$nbDeleted;
                    $this->deleteIndex($indexKey);
                }
            }

            $output->writeln(sprintf('<info>%d</info> indices removed!', $nbDeleted));
        }

        return Command::SUCCESS;
    }

    private function deleteIndex(string $indexName): void
    {
        try {
            $path = $indexName;
            $this->client->request($path, Request::DELETE);
        } catch (ExceptionInterface $deleteOldIndexException) {
            throw new \RuntimeException(\sprintf('Failed to delete index "%s" with message: "%s"', $indexName, $deleteOldIndexException->getMessage()), 0, $deleteOldIndexException);
        }
    }
}
