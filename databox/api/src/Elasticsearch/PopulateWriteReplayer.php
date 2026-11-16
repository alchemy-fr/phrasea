<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;

/**
 * Replays on the index built by a populate the live writes recorded by PopulateWriteJournal
 * while it was being built, once the alias points to it.
 */
final readonly class PopulateWriteReplayer
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private PopulateWriteJournal $journal,
        private ElasticSearchClient $elasticSearchClient,
        private SearchIndexer $searchIndexer,
    ) {
    }

    /**
     * The queries first, in their order: they are scripts that set a value, replaying them is
     * harmless. Then the documents, reindexed from the database (through the usual indexing
     * message), which has the last word.
     *
     * @param array{documents: string, queries: string} $journal           as returned by PopulateWriteJournal::detach()
     * @param string                                    $physicalIndexName the index built by the populate
     * @param class-string                              $modelClass        the entity indexed in it
     *
     * @return array{replayedQueries: int, replayedDocuments: int}
     */
    public function replay(array $journal, string $physicalIndexName, string $modelClass): array
    {
        $queries = 0;
        while (null !== $query = $this->journal->popQuery($journal['queries'])) {
            if (0 === $queries) {
                // the populate has not refreshed its index yet: its documents are not searchable
                $this->elasticSearchClient->request($physicalIndexName.'/_refresh');
            }

            $endpoint = PopulateWriteJournal::QUERY_DELETE === $query['operation'] ? '_delete_by_query' : '_update_by_query';
            $this->elasticSearchClient->request($physicalIndexName.'/'.$endpoint.'?conflicts=proceed', $query['body']);
            ++$queries;
        }

        $documents = 0;
        while ([] !== $ids = $this->journal->popDocuments($journal['documents'], self::BATCH_SIZE)) {
            // Upsert: the documents whose entity is gone are deleted from the index
            $this->searchIndexer->scheduleObjectsIndex($modelClass, $ids, Operation::Upsert);
            $documents += \count($ids);
        }

        return ['replayedQueries' => $queries, 'replayedDocuments' => $documents];
    }
}
