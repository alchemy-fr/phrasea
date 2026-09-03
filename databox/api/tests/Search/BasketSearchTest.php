<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Basket\Basket;
use Elastica\Document;

class BasketSearchTest extends AbstractSearchTest
{
    private const string USER_ID = KeycloakClientTestMock::USER_UID;

    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('basket');
    }

    public function testBasketsAreOrderedByCreatedAtDesc(): void
    {
        $this->createBasket([
            'name' => 'Oldest',
            'ownerId' => self::USER_ID,
            'createdAt' => '2024-01-01 10:00:00',
        ]);
        $this->createBasket([
            'name' => 'Newest',
            'ownerId' => self::USER_ID,
            'createdAt' => '2026-01-01 10:00:00',
        ]);
        $this->createBasket([
            'name' => 'Middle',
            'ownerId' => self::USER_ID,
            'createdAt' => '2025-01-01 10:00:00',
        ]);
        self::releaseIndex();

        $this->assertBasketNames(['Newest', 'Middle', 'Oldest'], $this->requestBaskets(['order' => 'createdAt']));
    }

    public function testBasketsSharingCreatedAtAreOrderedByIdAsc(): void
    {
        $createdAt = '2025-06-01 10:00:00';
        $this->createBasket([
            'id' => 'cccccccc-0000-4000-8000-000000000003',
            'name' => 'C',
            'ownerId' => self::USER_ID,
            'createdAt' => $createdAt,
        ]);
        $this->createBasket([
            'id' => 'aaaaaaaa-0000-4000-8000-000000000001',
            'name' => 'A',
            'ownerId' => self::USER_ID,
            'createdAt' => $createdAt,
        ]);
        $this->createBasket([
            'id' => 'bbbbbbbb-0000-4000-8000-000000000002',
            'name' => 'B',
            'ownerId' => self::USER_ID,
            'createdAt' => $createdAt,
        ]);
        self::releaseIndex();

        $this->assertBasketNames(['A', 'B', 'C'], $this->requestBaskets(['order' => 'createdAt']));
    }

    public function testBasketsWithoutCreatedAtComeLastInStableIdOrder(): void
    {
        $this->createBasket([
            'name' => 'Dated',
            'ownerId' => self::USER_ID,
            'createdAt' => '2020-01-01 10:00:00',
        ]);
        $undatedB = $this->createBasket([
            'id' => 'bbbbbbbb-0000-4000-8000-000000000002',
            'name' => 'Undated B',
            'ownerId' => self::USER_ID,
            'createdAt' => '2026-01-01 10:00:00',
        ]);
        $undatedA = $this->createBasket([
            'id' => 'aaaaaaaa-0000-4000-8000-000000000001',
            'name' => 'Undated A',
            'ownerId' => self::USER_ID,
            'createdAt' => '2026-02-01 10:00:00',
        ]);
        self::releaseIndex();

        // Even though they are the most recent ones, baskets whose indexed
        // creation date is not usable must be pushed after the dated ones.
        $this->removeIndexedCreatedAt($undatedA);
        $this->removeIndexedCreatedAt($undatedB);
        self::waitForESIndex('basket');

        $this->assertBasketNames(
            ['Dated', 'Undated A', 'Undated B'],
            $this->requestBaskets(['order' => 'createdAt'])
        );
    }

    public function testOrderIsNotAffectedByUpdates(): void
    {
        $old = $this->createBasket([
            'name' => 'Old',
            'ownerId' => self::USER_ID,
            'createdAt' => '2024-01-01 10:00:00',
        ]);
        $this->createBasket([
            'name' => 'Recent',
            'ownerId' => self::USER_ID,
            'createdAt' => '2025-01-01 10:00:00',
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $client->request('PUT', '/baskets/'.$old->getId(), [
            'json' => [
                'name' => 'Old (renamed)',
            ],
            'headers' => $this->getAuthHeaders(),
        ]);
        $this->assertResponseIsSuccessful();
        self::releaseIndex();

        // The basket has just been updated, but it must not come back on top.
        $this->assertBasketNames(
            ['Recent', 'Old (renamed)'],
            $this->requestBaskets(['order' => 'createdAt'])
        );
    }

    public function testOrderAlsoAppliesWithQuery(): void
    {
        $this->createBasket([
            'name' => 'Report old',
            'ownerId' => self::USER_ID,
            'createdAt' => '2024-01-01 10:00:00',
        ]);
        $this->createBasket([
            'name' => 'Report new',
            'ownerId' => self::USER_ID,
            'createdAt' => '2025-01-01 10:00:00',
        ]);
        $this->createBasket([
            'name' => 'Untitled',
            'ownerId' => self::USER_ID,
            'createdAt' => '2026-01-01 10:00:00',
        ]);
        self::releaseIndex();

        $this->assertBasketNames(
            ['Report new', 'Report old'],
            $this->requestBaskets([
                'order' => 'createdAt',
                'query' => 'Report',
            ])
        );
    }

    public function testInvalidOrderIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/baskets', [
            'query' => [
                'order' => 'updatedAt',
            ],
            'headers' => $this->getAuthHeaders(),
        ]);

        // Rejected by the declared enum of the "order" query parameter
        $this->assertResponseStatusCodeSame(422);
    }

    private function requestBaskets(array $query): array
    {
        $client = self::createClient();
        $response = $client->request('GET', '/baskets', [
            'query' => $query,
            'headers' => $this->getAuthHeaders(),
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray()['hydra:member'];
    }

    private function assertBasketNames(array $expectedNames, array $baskets): void
    {
        $this->assertSame($expectedNames, array_map(static fn (array $b): ?string => $b['name'] ?? null, $baskets));
    }

    private function removeIndexedCreatedAt(Basket $basket): void
    {
        $index = self::$documentIndices['basket'];
        $data = $index->getDocument($basket->getId())->getData();
        unset($data['createdAt']);

        $index->addDocument(new Document($basket->getId(), $data));
    }

    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER_ID),
        ];
    }
}
