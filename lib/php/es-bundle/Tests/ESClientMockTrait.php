<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests;

use Elastic\Elasticsearch\Endpoints\Cat;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\IndexManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Helpers to build a FOS Elastica client whose "indices"/"cat" endpoints are mocked.
 */
trait ESClientMockTrait
{
    /** @var Indices&MockObject */
    private Indices $indices;

    /** @var Cat&MockObject */
    private Cat $cat;

    /**
     * @return Client&MockObject
     */
    private function createClientMock(): Client
    {
        $this->indices = $this->createMock(Indices::class);
        $this->cat = $this->createMock(Cat::class);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($this->indices);
        $client->method('cat')->willReturn($this->cat);

        return $client;
    }

    /**
     * @return Elasticsearch&MockObject
     */
    private function createResponse(array $data): Elasticsearch
    {
        $response = $this->createMock(Elasticsearch::class);
        $response->method('asArray')->willReturn($data);

        return $response;
    }

    /**
     * @param array<string, string> $indices logical name => configured index/alias name
     *
     * @return IndexManager&MockObject
     */
    private function createIndexManagerMock(array $indices): IndexManager
    {
        $all = [];
        foreach ($indices as $logical => $target) {
            $index = $this->createMock(Index::class);
            $index->method('getName')->willReturn($target);
            $all[$logical] = $index;
        }

        $manager = $this->createMock(IndexManager::class);
        $manager->method('getAllIndexes')->willReturn($all);
        $manager->method('getIndex')->willReturnCallback(function (string $name) use ($all): Index {
            if (!isset($all[$name])) {
                throw new \InvalidArgumentException(sprintf('Unknown index "%s"', $name));
            }

            return $all[$name];
        });

        return $manager;
    }

    /**
     * Shape of the GET /_aliases response.
     *
     * @param array<string, list<string>> $aliasesByIndex physical index => aliases
     */
    private static function aliasesResponse(array $aliasesByIndex): array
    {
        $data = [];
        foreach ($aliasesByIndex as $index => $aliases) {
            $data[$index] = ['aliases' => array_fill_keys($aliases, new \stdClass())];
        }

        return $data;
    }
}
