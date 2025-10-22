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
        [$collectionId, $assetId] = $this->createStory($client);

        $this->checkRelation($client, $assetId, $collectionId);
    }

    public function testRemoveStoryByCollection(): void
    {
        self::enableFixtures();

        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $client = static::createClient();
        [$collectionId, $assetId] = $this->createStory($client);

        $client->request('DELETE', '/collections/'.$collectionId, [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/collections/'.$collectionId, [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/assets/'.$assetId, [
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
        [$collectionId, $assetId] = $this->createStory($client);

        $client->request('DELETE', '/assets/'.$assetId, [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/assets/'.$assetId, [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/collections/'.urlencode($collectionId), [
            'headers' => [
                'Authorization' => $adminAuthorization,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    private function createStory(Client $client): array
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

        return [$data['storyCollection']['id'], $data['id']];
    }

    private function checkRelation(Client $client, string $assetId, string $collectionId): void
    {
        $adminAuthorization = 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $response = $client->request('GET', '/collections/'.urlencode($collectionId), [
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

        $response = $client->request('GET', '/assets/'.urlencode($assetId), [
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
