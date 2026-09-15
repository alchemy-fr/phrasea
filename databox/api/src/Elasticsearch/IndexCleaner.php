<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastic\Elasticsearch\Traits\EndpointTrait;
use Elastica\Index;
use FOS\ElasticaBundle\Elastica\Client;

readonly class IndexCleaner
{
    use EndpointTrait;

    public function __construct(
        private Client $client,
        private Index $assetIndex,
        private Index $collectionIndex,
    ) {
    }

    public function removeWorkspaceFromIndex(string $workspaceId): void
    {
        foreach ([$this->assetIndex, $this->collectionIndex] as $index) {
            $this->deleteByQuery($index->getName(), [
                'term' => [
                    'workspaceId' => $workspaceId,
                ],
            ]);
        }
    }

    public function removeCollectionFromIndex(string $collectionId): void
    {
        $this->deleteByQuery($this->assetIndex->getName(), [
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
    private function deleteByQuery(string $indexName, array $query): void
    {
        $request = $this->createRequest(
            'POST',
            $indexName.'/_delete_by_query?conflicts=proceed&ignore_unavailable=true',
            [
                'Content-Type' => 'application/json',
            ],
            [
                'query' => $query,
            ]
        );

        $this->client->sendRequest($request);
    }
}
