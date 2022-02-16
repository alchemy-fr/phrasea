<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use FOS\ElasticaBundle\Elastica\Client;

class DeleteManager
{
    private Client $client;
    private array $indices;

    public function __construct(
        Client $client,
        array $indices
    ) {
        $this->client = $client;
        $this->indices = $indices;
    }

    public function deleteWorkspace(string $workspaceId): void
    {
        foreach ($this->indices as $index) {
            $indexName = $index->getName();
            $this->client->request($indexName.'/_delete_by_query',
                'POST',
                [
                    'query' => [
                        'term' => [
                            'workspaceId' => $workspaceId,
                        ]
                    ]
                ]
            );
        }
    }
}
