<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Index;
use FOS\ElasticaBundle\Elastica\Client;

class IndexCleaner
{
    private Client $client;
    private Index $assetIndex;
    private Index $collectionIndex;

    public function __construct(
        Client $client,
        Index $assetIndex,
        Index $collectionIndex
    ) {
        $this->client = $client;
        $this->assetIndex = $assetIndex;
        $this->collectionIndex = $collectionIndex;
    }

    public function removeWorkspaceFromIndex(string $workspaceId): void
    {
        foreach ([$this->assetIndex, $this->collectionIndex] as $index) {
            $indexName = $index->getName();
            $this->client->request($indexName.'/_delete_by_query',
                'POST',
                [
                    'query' => [
                        'term' => [
                            'workspaceId' => $workspaceId,
                        ],
                    ],
                ]
            );
        }
    }

    public function removeCollectionFromIndex(string $collectionId): void
    {
        $this->client->request($this->assetIndex->getName().'/_delete_by_query',
            'POST',
            [
                'query' => [
                    'term' => [
                        'referenceCollectionId' => $collectionId,
                    ],
                ],
            ]
        );
    }
}
