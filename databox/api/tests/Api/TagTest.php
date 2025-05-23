<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class TagTest extends AbstractSearchTestCase
{
    public function testGetTagCollection(): void
    {
        $limit = 10;
        self::enableFixtures();

        $client = static::createClient();
        $client->request('GET', '/tags?limit='.$limit, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $response = $client->request('GET', '/tags?limit='.$limit, [
            'query' => [
                'limit' => $limit,
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
            ],
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $resultCount = 2;
        $this->assertJsonContains([
            '@context' => '/contexts/tag',
            '@id' => '/tags',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => $resultCount,
            'hydra:view' => [
                '@type' => 'hydra:PartialCollectionView',
            ],
        ]);

        // Because test fixtures are automatically loaded between each test, you can assert on them
        $this->assertCount($resultCount, $response->toArray()['hydra:member']);

        // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
        // This generated JSON Schema is also used in the OpenAPI spec!
        $this->assertMatchesResourceCollectionJsonSchema(Tag::class);
    }

    public function testCreateTag(): void
    {
        self::enableFixtures();

        $response = static::createClient()->request('POST', '/tags', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'Foo',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'tag',
            'name' => 'Foo',
        ]);
        $this->assertMatchesRegularExpression('~^/tags/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Tag::class);
    }

    public function testCreateTagWithTranslations(): void
    {
        self::enableFixtures();

        $response = static::createClient()->request('POST', '/tags', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'Foo translation',
                'translations' => [
                    'name' => [
                        'fr' => 'Fou',
                    ],
                ],
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            'name' => 'Foo translation',
            'nameTranslated' => 'Foo translation',
            'translations' => [
                'name' => [
                    'fr' => 'Fou',
                ],
            ],
        ]);
        $this->assertMatchesRegularExpression('~^/tags/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
    }

    public function testCreateInvalidTag(): void
    {
        static::createClient()->request('POST', '/tags', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'Invalid payload',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Missing workspace',
        ]);
    }

    public function testUpdateTag(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        // findIriBy allows to retrieve the IRI of an item by searching for some of its properties.
        // ISBN 9786644879585 has been generated by Alice when loading test fixtures.
        // Because Alice use a seeded pseudo-random number generator, we're sure that this ISBN will always be generated.
        $iri = $this->findIriBy(Tag::class, ['name' => 'foo']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'name' => 'updated title',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'updated title',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'updated title',
        ]);
    }

    public function testDeleteTag(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Tag::class, ['name' => 'foo']);

        $client->request('DELETE', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('DELETE', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            // Through the container, you can access all your services from the tests, including the ORM, the mailer, remote API clients...
            static::getContainer()->get('doctrine')->getRepository(Tag::class)->findOneBy(['name' => 'foo'])
        );
    }
}
