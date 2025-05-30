<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class AssetTest extends AbstractSearchTestCase
{
    public function testGetAssetCollection(): void
    {
        $limit = 10;
        self::enableFixtures();
        $response = static::createClient()->request('GET', '/assets?limit='.$limit, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/asset',
            '@id' => '/assets',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 62,
            'hydra:view' => [
                '@id' => '/assets?limit='.$limit.'&page=1',
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => '/assets?limit='.$limit.'&page=1',
                'hydra:last' => '/assets?limit='.$limit.'&page=7',
                'hydra:next' => '/assets?limit='.$limit.'&page=2',
            ],
        ]);

        // Because test fixtures are automatically loaded between each test, you can assert on them
        $this->assertCount($limit, $response->toArray()['hydra:member']);

        // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
        // This generated JSON Schema is also used in the OpenAPI spec!
        $this->assertMatchesResourceCollectionJsonSchema(Asset::class);
    }

    public function testGetAssetCollectionWithClientScope(): void
    {
        $limit = 10;
        self::enableFixtures();
        $response = static::createClient()->request('GET', '/assets?limit='.$limit, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset:list asset:read'),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/asset',
            '@id' => '/assets',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 62,
            'hydra:view' => [
                '@id' => '/assets?limit='.$limit.'&page=1',
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => '/assets?limit='.$limit.'&page=1',
                'hydra:last' => '/assets?limit='.$limit.'&page=7',
                'hydra:next' => '/assets?limit='.$limit.'&page=2',
            ],
        ]);

        // Because test fixtures are automatically loaded between each test, you can assert on them
        $this->assertCount($limit, $response->toArray()['hydra:member']);

        // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
        // This generated JSON Schema is also used in the OpenAPI spec!
        $this->assertMatchesResourceCollectionJsonSchema(Asset::class);
    }

    public function testCreateAssetAndCopyByRef(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Dummy asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'extraMetadata' => [
                    'foo' => 'bar',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
            'extraMetadata' => [
                'foo' => 'bar',
            ],
        ]);
        $data = $response->toArray();
        $this->assertMatchesRegularExpression('~^/assets/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Asset::class);

        $client->request('POST', '/assets/copy', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'ids' => [$data['id']],
                'destination' => $this->findIriBy(Collection::class, [
                    'title' => 'Collection #1',
                ]),
                'byReference' => true,
                'extraMetadata' => [
                    'foo' => 'baz',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/assets/'.$data['id'], [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ])->toArray();

        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
            'extraMetadata' => [
                'foo' => 'bar',
            ],
            'collections' => [
                [
                    'title' => 'Collection #1',
                    'relationExtraMetadata' => [
                        'foo' => 'baz',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateAssetIsForbiddenWithoutWorkspace(): void
    {
        static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Invalid payload',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateAsset(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        // findIriBy allows to retrieve the IRI of an item by searching for some of its properties.
        // ISBN 9786644879585 has been generated by Alice when loading test fixtures.
        // Because Alice use a seeded pseudo-random number generator, we're sure that this ISBN will always be generated.
        $iri = $this->findIriBy(Asset::class, ['key' => 'foo']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'title' => 'updated title',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'title' => 'updated title',
        ]);
    }

    public function testDeleteAsset(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Asset::class, ['key' => 'foo']);

        $client->request('DELETE', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            // Through the container, you can access all your services from the tests, including the ORM, the mailer, remote API clients...
            static::getContainer()->get('doctrine')->getRepository(Asset::class)->findOneBy(['key' => 'foo'])
        );
    }
}
