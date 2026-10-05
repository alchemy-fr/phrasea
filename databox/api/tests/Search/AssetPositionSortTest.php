<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;

class AssetPositionSortTest extends AbstractSearchTest
{
    public function testSortByPositionInCollection(): void
    {
        [$collection, $assets] = $this->createOrderedCollection();
        [$a, $b, $c] = $assets;

        $client = self::createClient();

        $this->assertSame([$a, $b, $c], $this->search($client, [
            'parent' => $collection->getId(),
            'order' => ['@position' => 'ASC'],
        ]));
        $this->assertSame([$c, $b, $a], $this->search($client, [
            'parent' => $collection->getId(),
            'order' => ['@position' => 'DESC'],
        ]));
    }

    public function testSortByPositionReflectsAMove(): void
    {
        [$collection, $assets] = $this->createOrderedCollection();
        [$a, $b, $c] = $assets;

        $em = self::getEntityManager();
        $relations = $em->getRepository(CollectionAsset::class)
            ->findBy(['collection' => $collection->getId()]);
        foreach ($relations as $relation) {
            // Reverse the ranks behind the API's back, to prove the sort reads them
            $relation->setPosition(2 - $relation->getPosition());
        }
        $em->flush();
        self::releaseIndex();

        $this->assertSame([$c, $b, $a], $this->search(self::createClient(), [
            'parent' => $collection->getId(),
            'order' => ['@position' => 'ASC'],
        ]));
    }

    public function testSortByPositionWithoutASingleContainer(): void
    {
        $this->createOrderedCollection();

        self::createClient()->request('GET', '/assets?order[@position]=ASC');
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @return array{Collection, string[]} the collection and its asset IDs, in rank order
     */
    private function createOrderedCollection(): array
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Ordered',
            'public' => true,
        ]);

        $assetIds = [];
        foreach (['A', 'B', 'C'] as $name) {
            $assetIds[] = $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'collectionId' => $collection->getId(),
            ])->getId();
        }

        self::releaseIndex();

        return [$collection, $assetIds];
    }

    /**
     * @return string[] the matching asset IDs, in the order served
     */
    private function search(object $client, array $query): array
    {
        $response = $client->request('GET', '/assets?'.http_build_query($query));
        $data = $this->getDataFromResponse($response, 200)['hydra:member'];

        return array_map(fn (array $item): string => $item['id'], $data);
    }

    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }
}
