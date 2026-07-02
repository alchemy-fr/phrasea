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
            $indexName = $index->getName();

            $request = $this->createRequest(
                'POST',
                $indexName.'/_delete_by_query',
                [
                    'Content-Type' => 'application/json',
                ],
                [
                    'query' => [
                        'term' => [
                            'workspaceId' => $workspaceId,
                        ],
                    ],
                ]
            );

            $this->client->sendRequest($request);
        }
    }

    public function removeCollectionFromIndex(string $collectionId): void
    {
        $request = $this->createRequest('POST', $this->assetIndex->getName().'/_delete_by_query',
            [
                'Content-Type' => 'application/json',
            ],
            [
                'query' => [
                    'term' => [
                        'referenceCollectionId' => $collectionId,
                    ],
                ],
            ]
        );
        $this->client->sendRequest($request);
    }
}
