<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class CollectionAssetPositionTest extends AbstractSearchTestCase
{
    public function testNewRelationsAreAppendedAtTheEnd(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        $assetIds = $this->seedCollection($client, $collectionIri, 3);

        $this->assertSame([
            [$assetIds[0], 0],
            [$assetIds[1], 1],
            [$assetIds[2], 2],
        ], $this->listOrder($client, '/collections/'.$this->getId($collectionIri).'/assets'));
    }

    public function testMoveAssetToTheTop(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        [$first, $second, $third] = $this->seedCollection($client, $collectionIri, 3);

        $this->move($client, $third, $collectionIri, 0);

        $this->assertSame([
            [$third, 0],
            [$first, 1],
            [$second, 2],
        ], $this->listOrder($client, '/collections/'.$this->getId($collectionIri).'/assets'));
    }

    public function testMoveBeyondTheEndIsClampedToTheLastRank(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        [$first, $second, $third] = $this->seedCollection($client, $collectionIri, 3);

        $this->move($client, $first, $collectionIri, 99);

        $this->assertSame([
            [$second, 0],
            [$third, 1],
            [$first, 2],
        ], $this->listOrder($client, '/collections/'.$this->getId($collectionIri).'/assets'));
    }

    public function testMoveAssetInStory(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        [$storyAssetId, $storyCollectionId] = $this->createStory($client);
        $storyIri = '/assets/'.$storyAssetId;
        [$first, $second] = $this->seedCollection($client, '/collections/'.$storyCollectionId, 2);

        // The story is addressed by its asset IRI, not by its hidden collection
        $this->move($client, $second, $storyIri, 0);

        $this->assertSame([
            [$second, 0],
            [$first, 1],
        ], $this->listOrder($client, '/assets/'.$storyAssetId.'/story-assets'));
    }

    public function testMoveRequiresEditOnTheCollection(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        [$first] = $this->seedCollection($client, $collectionIri, 2);

        $client->request('PUT', '/assets/'.$first.'/position', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'destination' => $collectionIri,
                'position' => 0,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testMoveAnAssetThatIsNotInTheCollection(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        $this->seedCollection($client, $collectionIri, 1);
        $outsider = $this->createWorkspaceAsset();

        $client->request('PUT', '/assets/'.$outsider.'/position', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->adminJwt(),
            ],
            'json' => [
                'destination' => $collectionIri,
                'position' => 0,
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListingExcludesTrashedAssets(): void
    {
        self::enableFixtures();

        $client = static::createClient();
        $collectionIri = $this->findIriBy(Collection::class, ['name' => 'Collection #1']);
        [$kept, $trashed] = $this->seedCollection($client, $collectionIri, 2);

        $em = self::getEntityManager();
        $em->find(Asset::class, $trashed)->delete();
        $em->flush();
        self::releaseIndex();

        // The admin may read a trashed asset, and would restore it from the trash,
        // yet the listing must not show it: what is visible is decided by AssetSearch
        // and its default filters, not by a per-item vote on the page
        $this->assertSame([$kept], array_column(
            $this->listOrder($client, '/collections/'.$this->getId($collectionIri).'/assets'),
            0,
        ));
    }

    public function testListingAnUnreadableCollection(): void
    {
        self::enableFixtures();

        $hidden = $this->createCollection([
            'workspace' => self::getEntityManager()->getRepository(Workspace::class)->findOneBy(['slug' => 'test-workspace']),
            'name' => 'Hidden',
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
        ]);

        static::createClient()->request('GET', '/collections/'.$hidden->getId().'/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @return string[] the seeded asset IDs, in insertion order
     */
    private function seedCollection(Client $client, string $collectionIri, int $count): array
    {
        $assetIds = [];
        for ($i = 0; $i < $count; ++$i) {
            $assetId = $this->createWorkspaceAsset();
            $client->request('POST', '/collection-assets', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->adminJwt(),
                ],
                'json' => [
                    'collection' => $collectionIri,
                    'asset' => '/assets/'.$assetId,
                ],
            ]);
            $this->assertResponseIsSuccessful();
            $assetIds[] = $assetId;
        }
        self::releaseIndex();

        return $assetIds;
    }

    private function move(Client $client, string $assetId, string $destination, int $position): void
    {
        $client->request('PUT', '/assets/'.$assetId.'/position', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->adminJwt(),
            ],
            'json' => [
                'destination' => $destination,
                'position' => $position,
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::releaseIndex();
    }

    /**
     * @return array<int, array{string, int}> [assetId, position] pairs, as served
     */
    private function listOrder(Client $client, string $uri): array
    {
        $response = $client->request('GET', $uri, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->adminJwt(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);

        return array_map(fn (array $item): array => [
            $this->getId($item['asset']['@id']),
            $item['position'],
        ], $response->toArray()['hydra:member']);
    }

    /**
     * @return array{string, string} the story asset ID and its collection ID
     */
    private function createStory(Client $client): array
    {
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->adminJwt(),
            ],
            'json' => [
                'name' => 'An ordered story',
                'workspace' => $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']),
                'isStory' => true,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        return [$data['id'], $data['storyCollection']['id']];
    }

    /**
     * An asset of the fixture workspace, owned by the admin so that it is fully readable.
     */
    private function createWorkspaceAsset(bool $public = true): string
    {
        return $this->createAsset([
            'workspace' => self::getEntityManager()->getRepository(Workspace::class)->findOneBy(['slug' => 'test-workspace']),
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
            'public' => $public,
        ])->getId();
    }

    /**
     * The listing reads Elasticsearch, so the index has to catch up with the seeding.
     */
    private static function releaseIndex(): void
    {
        self::populateSearchIndices();
    }

    private function getId(string $iri): string
    {
        return substr($iri, strrpos($iri, '/') + 1);
    }

    private function adminJwt(): string
    {
        return KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
    }
}
