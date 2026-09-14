<?php

namespace Alchemy\ESBundle\Service;

use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class IndexRemover
{
    public const string REASON_PREFIXED = 'prefixed';
    public const string REASON_ALIASED = 'aliased';
    public const string REASON_OLD = 'old';

    public function __construct(
        private IndexManager $indexManager,
        private Client $client,
    ) {
    }

    /**
     * Computes the physical ES indices that would be removed, without touching anything.
     *
     * @return array<string, array<string, string>> Logical index name => [physical index name => reason]
     */
    public function collectIndicesToRemove(
        ?string $indexArg = null,
        bool $oldsOnly = false,
        bool $removeOlds = false,
        bool $forcePrefix = false,
    ): array {
        $indices = null === $indexArg ? array_keys($this->indexManager->getAllIndexes()) : [$indexArg];
        $removeOlds = $oldsOnly || $removeOlds;

        $aliasesData = $this->client->indices()->getAlias()->asArray();

        $plan = [];
        foreach ($indices as $i) {
            $plan[$i] = [];
            $indexName = $this->indexManager->getIndex($i)->getName();

            foreach ($aliasesData as $indexKey => $c) {
                if ($forcePrefix && str_starts_with($indexKey, $i.'_')) {
                    $plan[$i][$indexKey] = self::REASON_PREFIXED;

                    continue;
                }

                if (isset($c['aliases'][$indexName])) {
                    if (!$oldsOnly) {
                        $plan[$i][$indexKey] = self::REASON_ALIASED;
                    }

                    continue;
                }

                if ($removeOlds && 1 === preg_match('#^'.preg_quote($indexName).'_\d{4}-\d{2}-\d{2}-\d{6}$#', $indexKey)) {
                    $plan[$i][$indexKey] = self::REASON_OLD;
                }
            }
        }

        return $plan;
    }

    public function removeIndices(
        ?string $indexArg = null,
        bool $oldsOnly = false,
        bool $removeOlds = false,
        bool $forcePrefix = false,
        ?OutputInterface $output = null,
    ): void {
        $this->removePlannedIndices(
            $this->collectIndicesToRemove($indexArg, $oldsOnly, $removeOlds, $forcePrefix),
            $output
        );
    }

    /**
     * @param array<string, array<string, string>> $plan as returned by collectIndicesToRemove()
     */
    public function removePlannedIndices(array $plan, ?OutputInterface $output = null): void
    {
        foreach ($plan as $i => $physicalIndices) {
            $output?->writeln(sprintf('Delete index <comment>%s</comment>', $i));

            foreach ($physicalIndices as $indexKey => $reason) {
                $output?->writeln(sprintf('Removing %s index <comment>%s</comment>', $reason, $indexKey));
                $this->deleteIndex($indexKey);
            }

            $output?->writeln(sprintf('<info>%d</info> indices removed!', count($physicalIndices)));
        }
    }

    private function deleteIndex(string $indexName): void
    {
        try {
            $this->client->indices()->delete(['index' => $indexName]);
        } catch (ElasticsearchException|TransportException $deleteOldIndexException) {
            throw new \RuntimeException(\sprintf('Failed to delete index "%s" with message: "%s"', $indexName, $deleteOldIndexException->getMessage()), 0, $deleteOldIndexException);
        }
    }
}
