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
        private Index $attributeEntityIndex,
        private PopulateWriteJournal $populateWriteJournal,
    ) {
    }

    public function updateByQuery(string $indexName, ?array $query, string|array $script): void
    {
        $index = $this->getIndexName($indexName);

        $data = ['script' => $script];
        if (!empty($query)) {
            $data['query'] = $query;
        }

        $this->populateWriteJournal->recordQuery(self::getLogicalIndexName($indexName), PopulateWriteJournal::QUERY_UPDATE, $data);

        $this->request($index.'/_refresh');
        $this->request($index.'/_update_by_query?conflicts=proceed', $data);
    }

    public function deleteByQuery(string $indexName, array $query): void
    {
        $index = $this->getIndexName($indexName);
        $data = [
            'query' => $query,
        ];

        $this->populateWriteJournal->recordQuery(self::getLogicalIndexName($indexName), PopulateWriteJournal::QUERY_DELETE, $data);

        $this->request($index.'/_delete_by_query', $data);
    }

    /**
     * "attributeEntity" => "attribute_entity".
     */
    private static function getLogicalIndexName(string $key): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', $key));
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
                'Accept' => 'application/json',
            ],
            $data
        );

        return $this->client->sendRequest($request);
    }
}
