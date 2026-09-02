<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Collection;
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
            '@type' => 'Collection',
            'totalItems' => 0,
            'view' => [
                '@id' => '/collections?limit='.$limit,
                '@type' => 'PartialCollectionView',
            ],
        ]);
        $this->assertCount(0, $response->toArray()['member']);

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
            '@type' => 'Collection',
            'totalItems' => 2,
            'view' => [
                '@id' => '/collections?limit='.$limit,
                '@type' => 'PartialCollectionView',
            ],
        ]);
        $this->assertCount(2, $response->toArray()['member']);
        $this->assertMatchesResourceCollectionJsonSchema(Collection::class);
    }
}
