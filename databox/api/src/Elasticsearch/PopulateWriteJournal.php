<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\ESBundle\Indexer\IndexWriteJournalInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Records the live writes made to an index while it is being populated.
 *
 * A populate copies the database into a new physical index, then switches the alias to it.
 * Meanwhile, the live writes go through the alias to the old index: a document already
 * copied (or read from the database before the change and copied after) would keep its
 * old ACL, deletion flag… in the new index. Every write made while the populate lock of
 * the index is held is therefore recorded here — before being sent, so that a write the
 * journal misses can only land after the switch, in the new index — and replayed on the
 * new index by PopulateWriteReplayer once the alias points to it:
 * - the ids of the documents indexed or deleted one by one (a Redis set),
 * - the update_by_query / delete_by_query requests (a Redis list, in their order).
 */
#[AsAlias(IndexWriteJournalInterface::class)]
final readonly class PopulateWriteJournal implements IndexWriteJournalInterface
{
    private const string DOCUMENTS_PREFIX = 'es_populate_dirty_';
    private const string QUERIES_PREFIX = 'es_populate_dirty_queries_';

    public const string QUERY_UPDATE = 'update';
    public const string QUERY_DELETE = 'delete';

    public function __construct(
        private PopulateLockManager $lockManager,
    ) {
    }

    public function recordDocuments(string $indexName, array $ids): void
    {
        if ([] === $ids || !$this->lockManager->isLocked($indexName)) {
            return;
        }

        $this->lockManager->redis()->sAdd($this->getDocumentsKey($indexName), array_values(array_map(strval(...), $ids)));
    }

    /**
     * @param self::QUERY_* $operation
     * @param array         $body      the body of the _update_by_query / _delete_by_query request
     */
    public function recordQuery(string $indexName, string $operation, array $body): void
    {
        if (!$this->lockManager->isLocked($indexName)) {
            return;
        }

        $this->lockManager->redis()->rPush($this->getQueriesKey($indexName), json_encode([
            'operation' => $operation,
            'body' => $body,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Forgets what a previous populate of the index left behind.
     */
    public function reset(string $indexName): void
    {
        $this->lockManager->redis()->del([$this->getDocumentsKey($indexName), $this->getQueriesKey($indexName)]);
    }

    /**
     * Moves what was recorded during the populate aside, so that the lock can be released
     * (and the index populated again) while it is replayed.
     *
     * @return array{documents: string, queries: string} the Redis keys of the detached journal
     */
    public function detach(string $indexName, string $runId): array
    {
        $redis = $this->lockManager->redis();
        $detached = [];
        foreach (['documents' => $this->getDocumentsKey($indexName), 'queries' => $this->getQueriesKey($indexName)] as $name => $key) {
            $detached[$name] = $key.'_replay_'.$runId;
            $redis->del([$detached[$name]]);
            // writers only add to these keys: once there, the key cannot vanish before the rename
            if ($redis->exists($key)) {
                $redis->rename($key, $detached[$name]);
            }
        }

        return $detached;
    }

    /**
     * @return list<string> up to $count ids, removed from the detached journal
     */
    public function popDocuments(string $detachedKey, int $count): array
    {
        return $this->lockManager->redis()->sPop($detachedKey, $count);
    }

    /**
     * @return array{operation: self::QUERY_*, body: array}|null the oldest query, removed from the detached journal
     */
    public function popQuery(string $detachedKey): ?array
    {
        $value = $this->lockManager->redis()->lPop($detachedKey);
        if (null === $value) {
            return null;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getDocumentsKey(string $indexName): string
    {
        return $this->lockManager->getKey(self::DOCUMENTS_PREFIX, $indexName);
    }

    private function getQueriesKey(string $indexName): string
    {
        return $this->lockManager->getKey(self::QUERIES_PREFIX, $indexName);
    }
}
