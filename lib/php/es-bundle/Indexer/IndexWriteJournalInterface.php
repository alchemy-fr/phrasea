<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

/**
 * Told about every document written to an index outside of its populate.
 *
 * A populate builds a new physical index and only switches the alias to it at the end:
 * meanwhile, the live writes go to the old index. An implementation keeps track of the
 * documents written while the index is being populated, so that they can be written
 * again to the new index once the alias points to it.
 */
interface IndexWriteJournalInterface
{
    /**
     * Called before the documents are written: a write that comes after the switch of the
     * alias reaches the new index anyway, only a write recorded beforehand can be missed.
     *
     * @param string       $indexName logical index name
     * @param list<string> $ids
     */
    public function recordDocuments(string $indexName, array $ids): void;
}
