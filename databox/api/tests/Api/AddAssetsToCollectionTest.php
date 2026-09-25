<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class AddAssetsToCollectionTest extends AbstractSearchTestCase
{
    public function testAddAssetsToCollectionRequiresAssetCreatePermission(): void
    {
        self::enableFixtures();

        static::createClient()->request('POST', '/assets/add-to-collection', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'destination' => $this->findIriBy(Collection::class, ['name' => 'Collection #1']),
                'ids' => [$this->getAssetId('foo')],
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAddAssetsToCollectionIsIdempotent(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionId = $this->getCollectionId('Collection #1');
        $assetIds = [$this->getAssetId('foo'), $this->getAssetId('bar')];

        $this->addToDestination($client, '/collections/'.$collectionId, $assetIds);
        $this->assertEqualsCanonicalizing($assetIds, $this->getCollectionAssetIds($collectionId));

        // Replaying the same call must not create duplicates
        $this->addToDestination($client, '/collections/'.$collectionId, $assetIds);
        $this->assertEqualsCanonicalizing($assetIds, $this->getCollectionAssetIds($collectionId));
    }

    public function testAddAssetsToStory(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        [$storyAssetId, $storyCollectionId] = $this->createStory($client);
        $assetIds = [$this->getAssetId('foo'), $this->getAssetId('bar')];

        $this->addToDestination($client, '/assets/'.$storyAssetId, $assetIds);
        $this->assertEqualsCanonicalizing($assetIds, $this->getCollectionAssetIds($storyCollectionId));
    }

    public function testAddStoryToItself(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        [$storyAssetId] = $this->createStory($client);

        $client->request('POST', '/assets/add-to-collection', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'destination' => '/assets/'.$storyAssetId,
                'ids' => [$storyAssetId],
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddAssetsToNonStoryAsset(): void
    {
        self::enableFixtures();

        static::createClient()->request('POST', '/assets/add-to-collection', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'destination' => '/assets/'.$this->getAssetId('bar'),
                'ids' => [$this->getAssetId('foo')],
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @param string[] $assetIds
     */
    private function addToDestination(Client $client, string $destination, array $assetIds): void
    {
        $client->request('POST', '/assets/add-to-collection', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'destination' => $destination,
                'ids' => $assetIds,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * @return array{string, string} the story asset ID and its collection ID
     */
    private function createStory(Client $client): array
    {
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'A story',
                'workspace' => $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']),
                'isStory' => true,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        return [$data['id'], $data['storyCollection']['id']];
    }

    private function getAssetId(string $key): string
    {
        return self::getEntityManager()
            ->getRepository(Asset::class)
            ->findOneBy(['key' => $key])
            ->getId();
    }

    private function getCollectionId(string $name): string
    {
        return self::getEntityManager()
            ->getRepository(Collection::class)
            ->findOneBy(['name' => $name])
            ->getId();
    }

    /**
     * @return string[]
     */
    private function getCollectionAssetIds(string $collectionId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        $collectionAssets = $em->getRepository(CollectionAsset::class)
            ->findBy(['collection' => $collectionId]);

        return array_map(fn (CollectionAsset $ca): string => $ca->getAsset()->getId(), $collectionAssets);
    }
}
