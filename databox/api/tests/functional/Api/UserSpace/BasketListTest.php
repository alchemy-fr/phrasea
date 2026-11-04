<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Basket\Basket;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * GET /baskets (served by Elasticsearch).
 * Ordering is covered by Search\BasketSearchTest.
 */
final class BasketListTest extends AbstractSearchTestCase
{
    use UserSpaceTestTrait;

    public function testListRequiresAuthentication(): void
    {
        $this->assertStatus(401, 'GET', '/baskets', null);
    }

    public function testArchivedBasketsAreExcludedByDefault(): void
    {
        $active = $this->createBasket(['name' => 'Active', 'ownerId' => self::USER]);
        $archived = $this->createArchivedBasket('Archived', self::USER);
        self::populateSearchIndices();

        $this->assertSame([$active->getId()], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));

        $this->assertSame(
            [$archived->getId()],
            $this->memberIds($this->apiJson('GET', '/baskets', self::USER, query: ['archived' => 'true']))
        );

        $this->assertSame(
            [$active->getId()],
            $this->memberIds($this->apiJson('GET', '/baskets', self::USER, query: ['archived' => 'false']))
        );

        $ids = $this->memberIds($this->apiJson('GET', '/baskets', self::USER, query: ['includeArchived' => 'true']));
        sort($ids);
        $expected = [$active->getId(), $archived->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testArchivingThroughApiMovesBasketToArchivedList(): void
    {
        $basket = $this->createBasket(['name' => 'Soon archived', 'ownerId' => self::USER]);
        self::populateSearchIndices();
        $this->assertSame([$basket->getId()], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));

        $this->apiJson('POST', '/baskets/'.$basket->getId().'/archive', self::USER, []);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('basket');

        $this->assertSame([], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));
        $this->assertSame(
            [$basket->getId()],
            $this->memberIds($this->apiJson('GET', '/baskets', self::USER, query: ['archived' => 'true']))
        );

        $this->apiJson('POST', '/baskets/'.$basket->getId().'/unarchive', self::USER, []);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('basket');

        $this->assertSame([$basket->getId()], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));
    }

    public function testSharedBasketIsListedForGrantee(): void
    {
        $own = $this->createBasket(['name' => 'Own', 'ownerId' => self::OTHER]);
        $shared = $this->createBasket(['name' => 'Shared', 'ownerId' => self::USER]);
        $this->createBasket(['name' => 'Private', 'ownerId' => self::USER]);
        $this->grantUserOnObject(self::OTHER, $shared, PermissionInterface::VIEW);
        self::populateSearchIndices();

        $ids = $this->memberIds($this->apiJson('GET', '/baskets', self::OTHER));
        sort($ids);
        $expected = [$own->getId(), $shared->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);

        $shared = array_values(array_filter(
            $this->members($this->apiJson('GET', '/baskets', self::OTHER)),
            static fn (array $b): bool => 'Shared' === $b['name'],
        ))[0];
        $this->assertFalse($shared['capabilities']['edit']);
        $this->assertFalse($shared['capabilities']['delete']);
    }

    public function testAdminListsAllBaskets(): void
    {
        $this->createBasket(['name' => 'U', 'ownerId' => self::USER]);
        $this->createBasket(['name' => 'O', 'ownerId' => self::OTHER]);
        self::populateSearchIndices();

        $this->assertCount(2, $this->members($this->apiJson('GET', '/baskets', self::ADMIN)));
    }

    public function testQueryMatchesNameAndDescriptionWithHighlights(): void
    {
        $byName = $this->createBasket(['name' => 'Holidays pictures', 'ownerId' => self::USER]);
        $byDescription = $this->createBasket(['name' => 'Misc', 'description' => 'Selection for holidays', 'ownerId' => self::USER]);
        $this->createBasket(['name' => 'Work', 'ownerId' => self::USER]);
        self::populateSearchIndices();

        $data = $this->apiJson('GET', '/baskets', self::USER, query: ['query' => 'holidays']);
        $ids = $this->memberIds($data);
        sort($ids);
        $expected = [$byName->getId(), $byDescription->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);

        foreach ($this->members($data) as $member) {
            if ($member['id'] === $byName->getId()) {
                $this->assertStringContainsString('[hl]', $member['nameHighlight']);
            } else {
                $this->assertStringContainsString('[hl]', $member['descriptionHighlight']);
                $this->assertSame('Selection for holidays', $member['description']);
            }
        }
    }

    public function testListIsPaginated(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->createBasket(['name' => 'B'.$i, 'ownerId' => self::USER]);
        }
        self::populateSearchIndices();

        $data = $this->apiJson('GET', '/baskets', self::USER, query: ['limit' => 2]);
        $this->assertCount(2, $this->members($data));
        $this->assertSame(3, $data['totalItems']);

        $page2 = $this->apiJson('GET', '/baskets', self::USER, query: ['limit' => 2, 'page' => 2]);
        $this->assertCount(1, $this->members($page2));
    }

    public function testDeletedBasketDisappearsFromList(): void
    {
        $basket = $this->createBasket(['name' => 'Deleted', 'ownerId' => self::USER]);
        self::populateSearchIndices();
        $this->assertSame([$basket->getId()], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));

        $this->apiJson('DELETE', '/baskets/'.$basket->getId(), self::USER, expectedStatus: 204);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('basket');

        $this->assertSame([], $this->memberIds($this->apiJson('GET', '/baskets', self::USER)));
    }

    private function createArchivedBasket(string $name, string $ownerId): Basket
    {
        $basket = $this->createBasket(['name' => $name, 'ownerId' => $ownerId, 'no_flush' => true]);
        $basket->archive();
        self::getEntityManager()->flush();

        return $basket;
    }
}
