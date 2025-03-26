<?php

declare(strict_types=1);

namespace Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class CollectionTest extends AbstractSearchTestCase
{
    public function testGetCollections(): void
    {
        $limit = 10;
        self::enableFixtures();

        $response = static::createClient()->request('GET', '/collections?limit='.$limit, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt(),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/collection',
            '@id' => '/collections',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 0,
            'hydra:view' => [
                '@id' => '/collections?limit='.$limit,
                '@type' => 'hydra:PartialCollectionView',
            ],
        ]);
        $this->assertCount(0, $response->toArray()['hydra:member']);

        $response = static::createClient()->request('GET', '/collections?limit='.$limit, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('collection:list collection:read'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/collection',
            '@id' => '/collections',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
            'hydra:view' => [
                '@id' => '/collections?limit='.$limit,
                '@type' => 'hydra:PartialCollectionView',
            ],
        ]);
        $this->assertCount(2, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Collection::class);
    }
}
