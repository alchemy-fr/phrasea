<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Index;

readonly class IndexCleaner
{
    public function __construct(
        private Index $assetIndex,
        private Index $collectionIndex,
    ) {
    }

    public function removeWorkspaceFromIndex(string $workspaceId): void
    {
        foreach ([$this->assetIndex, $this->collectionIndex] as $index) {
            $this->deleteByQuery($index, [
                'term' => [
                    'workspaceId' => $workspaceId,
                ],
            ]);
        }
    }

    public function removeCollectionFromIndex(string $collectionId): void
    {
        $this->deleteByQuery($this->assetIndex, [
            'term' => [
                'referenceCollectionId' => $collectionId,
            ],
        ]);
    }

    /**
     * Cleaning up an index is best-effort: the entity is already gone from the
     * database. `ignore_unavailable` covers an index that was never created (or
     * already dropped) and `conflicts=proceed` covers documents concurrently
     * reindexed — neither should abort a deletion.
     */
    private function deleteByQuery(Index $index, array $query): void
    {
        $index->deleteByQuery(['query' => $query], [
            'conflicts' => 'proceed',
            'ignore_unavailable' => true,
        ]);
    }
}
