<?php

namespace App\Elasticsearch;

use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use FOS\ElasticaBundle\Elastica\Client;

final readonly class ElasticSearchClient
{
    public function __construct(
        private Client $client,
        private Index $assetIndex,
    ) {
    }

    public function updateByQuery(string $indexName, array $query, string|array $script): void
    {
        $index = $this->getIndexName($indexName);

        $this->request($index.'/_refresh');

        $this->request($index.'/_update_by_query?conflicts=proceed', [
            'script' => $script,
            'query' => $query,
        ]);
    }

    public function getIndexName(string $key): string
    {
        return $this->{$key.'Index'}->getName();
    }

    public function request(string $path, array $data = [], string $method = Request::POST): Response
    {
        return $this->client->request($path, $method, $data);
    }
}
