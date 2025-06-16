<?php

namespace Alchemy\ESBundle\Service;

use Elastica\Exception\ExceptionInterface;
use Elastica\Request;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class IndexRemover
{
    public function __construct(
        private IndexManager $indexManager,
        private Client $client,
    ) {
    }

    public function removeIndices(
        ?string $indexArg = null,
        bool $oldsOnly = false,
        bool $removeOlds = false,
        bool $forcePrefix = false,
        ?OutputInterface $output = null,
    ): void {
        $indices = null === $indexArg ? array_keys($this->indexManager->getAllIndexes()) : [$indexArg];

        $response = $this->client->request('_aliases');
        $aliasesData = $response->getData();

        foreach ($indices as $i) {
            $output?->writeln(sprintf('Delete index <comment>%s</comment>', $i));
            $nbDeleted = 0;
            $index = $this->indexManager->getIndex($i);

            $removeOlds = $oldsOnly || $removeOlds;
            $indexName = $index->getName();

            foreach ($aliasesData as $indexKey => $c) {
                if ($forcePrefix && str_starts_with($indexKey, $i.'_')) {
                    ++$nbDeleted;
                    $this->deleteIndex($indexKey);

                    continue;
                }

                if (isset($c['aliases'][$indexName])) {
                    if (!$oldsOnly) {
                        $output?->writeln(sprintf('Removing aliased index <comment>%s</comment>', $indexKey));
                        ++$nbDeleted;
                        $this->deleteIndex($indexKey);
                    }

                    continue;
                }

                if ($removeOlds && 1 === preg_match('#^'.preg_quote($indexName).'_\d{4}-\d{2}-\d{2}-\d{6}$#', $indexKey)) {
                    $output?->writeln(sprintf('Removing old index <comment>%s</comment>', $indexKey));
                    ++$nbDeleted;
                    $this->deleteIndex($indexKey);
                }
            }

            $output?->writeln(sprintf('<info>%d</info> indices removed!', $nbDeleted));
        }
    }

    private function deleteIndex(string $indexName): void
    {
        try {
            $this->client->request($indexName, Request::DELETE);
        } catch (ExceptionInterface $deleteOldIndexException) {
            throw new \RuntimeException(\sprintf('Failed to delete index "%s" with message: "%s"', $indexName, $deleteOldIndexException->getMessage()), 0, $deleteOldIndexException);
        }
    }
}
