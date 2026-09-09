<?php

declare(strict_types=1);

namespace App\Tests\Search;

/**
 * "@collection" / "parents" match an asset of a collection or of any of its
 * descendants, whereas "@directCollection" / "directCollections" only match
 * assets directly attached to one of the requested collections.
 */
class AssetDirectCollectionSearchTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function testDirectCollectionFilterIgnoresDescendantCollections(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $parent = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Parent',
            'public' => true,
        ]);
        $child = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Child',
            'parent' => $parent,
            'public' => true,
        ]);
        $other = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Other',
            'public' => true,
        ]);

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In parent',
            'public' => true,
            'collectionId' => $parent->getId(),
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In child',
            'public' => true,
            'collectionId' => $child->getId(),
        ]);
        $inBoth = $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In both',
            'public' => true,
            'collectionId' => $other->getId(),
        ]);

        $em = self::getEntityManager();
        $em->persist($inBoth->addToCollection($child));
        $em->flush();

        self::releaseIndex();

        $parentId = $parent->getId();
        $childId = $child->getId();
        $otherId = $other->getId();

        // Recursive contract, for reference
        $this->assertSearch(['parents' => [$parentId]], ['In both', 'In child', 'In parent']);
        $this->assertSearch(['conditions' => [sprintf('@collection = "%s"', $parentId)]], ['In both', 'In child', 'In parent']);

        // Direct membership only
        $this->assertSearch(['directCollections' => [$parentId]], ['In parent']);
        $this->assertSearch(['directCollections' => [$childId]], ['In both', 'In child']);
        $this->assertSearch(['directCollections' => [$otherId]], ['In both']);

        // A list matches assets having at least one membership in it, without duplicating them
        $this->assertSearch(['directCollections' => [$parentId, $childId]], ['In both', 'In child', 'In parent']);
        $this->assertSearch(['directCollections' => [$childId, $otherId]], ['In both', 'In child']);

        // Same semantics through the AQL attribute
        $this->assertSearch(['conditions' => [sprintf('@directCollection = "%s"', $parentId)]], ['In parent']);
        $this->assertSearch(['conditions' => [sprintf('@directCollection IN ("%s", "%s")', $parentId, $childId)]], ['In both', 'In child', 'In parent']);
        $this->assertSearch(['conditions' => [sprintf('@directCollection NOT IN ("%s")', $childId)]], ['In parent']);
    }

    public function testDirectCollectionsFilterWithUnknownCollection(): void
    {
        $this->createWorkspace(['public' => true]);

        $client = self::createClient();
        $client->request('GET', '/assets', [
            'query' => [
                'directCollections' => ['fa4d6a1a-0000-4000-8000-000000000000'],
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDirectCollectionAqlAttributeWithInvalidCollectionId(): void
    {
        $this->createWorkspace(['public' => true]);

        $client = self::createClient();
        $client->request('GET', '/assets', [
            'query' => [
                'conditions' => ['@directCollection = "not-an-uuid"'],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @param array<string, mixed> $query
     * @param string[]             $expectedNames
     */
    private function assertSearch(array $query, array $expectedNames): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'query' => $query,
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $names = array_map(fn (array $asset): ?string => $asset['name'] ?? null, $data);
        sort($names);

        self::assertSame($expectedNames, $names, sprintf('Unexpected results for %s', json_encode($query)));
    }
}
