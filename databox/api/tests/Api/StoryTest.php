<?php

declare(strict_types=1);

namespace Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class StoryTest extends AbstractSearchTestCase
{
    public function testCreateStory(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionId = "";
        $assetId = "";

        $this->createStory($client, $assetId,$collectionId);

        $this->checkRelation($client, $assetId, $collectionId);
    }

    public function testRemoveStoryByCollection(): void
    {
        self::enableFixtures();

        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $client = static::createClient();
        $collectionId = "";
        $assetId = "";
        $this->createStory($client, $assetId,$collectionId);

        $client->request('DELETE', '/collections/' . urlencode($collectionId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/collections/' . urlencode($collectionId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/assets/' . urlencode($assetId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRemoveStoryByAsset(): void
    {
        self::enableFixtures();

        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $client = static::createClient();
        $collectionId = "";
        $assetId = "";
        $this->createStory($client, $assetId,$collectionId);

        $client->request('DELETE', '/assets/' . urlencode($assetId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/assets/' . urlencode($assetId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/collections/' . urlencode($collectionId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateStoryManualLink(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                // this title should become null when the collection becomes a storyCollection
                'title' => 'Dummy story-collection',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ])
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $collectionId = $response->toArray()['id'];
        $collectionIri = $response->toArray()['@id'];

        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Dummy story-asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'storyCollection' => $collectionIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $assetId = $response->toArray()['id'];

        $this->checkRelation($client, $assetId, $collectionId);
    }

    private function createStory(Client $client, string &$assetId, string &$collectionId): void
    {
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Dummy story-asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'isStory' => true,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $data = $response->toArray();
        $this->assertArrayHasKey('storyCollection', $data);
        $this->assertIsArray($data['storyCollection']);
        // dump($response->toArray());

        $assetId = $data['id'];
        $collectionId = $data['storyCollection']['id'];
    }

    private function checkRelation(Client $client, string $assetId, string $collectionId): void
    {
        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $response = $client->request('GET', '/collections/' . urlencode($collectionId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'collection',
            'storyAsset' => [
                '@type' => 'asset',
                'id' => $assetId,
            ],
        ]);
        // a storyCollection has (null) title
        // because apiPlatform is set to skip null values, we check it's not there
        $this->assertArrayNotHasKey('title', $response->toArray());

        $response = $client->request('GET', '/assets/' . urlencode($assetId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy story-asset',
            'storyCollection' => [
                '@type' => 'collection',
                'id' => $collectionId,
            ],
        ]);
        $this->assertArrayNotHasKey('title', $response->toArray()['storyCollection']);
    }
}
