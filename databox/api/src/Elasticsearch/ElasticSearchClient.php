<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Elasticsearch\Traits\EndpointTrait;
use Elastica\Index;
use FOS\ElasticaBundle\Elastica\Client;

final readonly class ElasticSearchClient
{
    use EndpointTrait;

    public function __construct(
        private Client $client,
        private Index $assetIndex,
        private Index $collectionIndex,
        private Index $attributeIndex,
    ) {
    }

    public function updateByQuery(string $indexName, ?array $query, string|array $script): void
    {
        $index = $this->getIndexName($indexName);

        $data = ['script' => $script];
        if (!empty($query)) {
            $data['query'] = $query;
        }

        $this->request($index.'/_refresh');
        $this->request($index.'/_update_by_query?conflicts=proceed', $data);
    }

    public function deleteByQuery(string $indexName, array $query): void
    {
        $index = $this->getIndexName($indexName);

        $this->request($index.'/_delete_by_query', [
            'query' => $query,
        ]);
    }

    public function getIndexName(string $key): string
    {
        return $this->{$key.'Index'}->getName();
    }

    public function request(string $path, array $data = [], string $method = 'POST'): Elasticsearch
    {
        $request = $this->createRequest(
            $method,
            $path,
            [
                'Content-Type' => 'application/json',
            ],
            $data
        );

        return $this->client->sendRequest($request);
    }
}
