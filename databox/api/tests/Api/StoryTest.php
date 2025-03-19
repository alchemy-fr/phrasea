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

        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $client = static::createClient();
        $collectionId = "";
        $assetId = "";
        $this->createStory($client,$collectionId, $assetId);

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

    public function testRemoveStoryByCollection(): void
    {
        self::enableFixtures();

        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $client = static::createClient();
        $collectionId = "";
        $assetId = "";
        $this->createStory($client,$collectionId, $assetId);

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
        $this->createStory($client,$collectionId, $assetId);

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

    private function createStory(Client $client, string &$collectionId, string &$assetId): void
    {
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
    }
}
