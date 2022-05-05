<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\FixturesTrait;
use App\Tests\Search\SearchTestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

class AssetTest extends ApiTestCase
{
    use FixturesTrait;
    use SearchTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::fixturesBootKernel($options);
        self::bootSearch(static::$kernel);

        return static::$kernel;
    }

    public function testGetAssetCollection(): void
    {
        self::enableFixtures();
        $response = static::createClient()->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
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
                '@id' => '/assets?page=1',
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => '/assets?page=1',
                'hydra:last' => '/assets?page=3',
                'hydra:next' => '/assets?page=2',
            ],
        ]);

        // Because test fixtures are automatically loaded between each test, you can assert on them
        $this->assertCount(30, $response->toArray()['hydra:member']);

        // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
        // This generated JSON Schema is also used in the OpenAPI spec!
        $this->assertMatchesResourceCollectionJsonSchema(Asset::class);
    }

    public function testCreateAsset(): void
    {
        $response = static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'title' => 'Dummy asset',
                'workspace' => $this->findIriBy(Workspace::class, []),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
        ]);
        $this->assertMatchesRegularExpression('~^/assets/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Asset::class);
    }

    public function testCreateInvalidAsset(): void
    {
        static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'title' => 'Invalid payload',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/ConstraintViolationList',
            '@type' => 'ConstraintViolationList',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'workspace: This value should not be null.',
        ]);
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
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
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
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
        // Through the container, you can access all your services from the tests, including the ORM, the mailer, remote API clients...
            static::getContainer()->get('doctrine')->getRepository(Asset::class)->findOneBy(['key' => 'foo'])
        );
    }
}