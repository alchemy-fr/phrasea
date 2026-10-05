<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Basket item operations (no Elasticsearch involved).
 * Listing (GET /baskets) is covered by BasketListTest.
 */
final class BasketApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    public function testCreateRequiresAuthentication(): void
    {
        $this->markTestIncomplete('BUG: anonymous POST /baskets answers 400 "You must provide ownerId" instead of 401: the Post operation has no `security` (only securityPostValidation), unlike POST /profiles (src/Entity/Basket/Basket.php:87)');

        $this->assertStatus(401, 'POST', '/baskets', null, ['name' => 'Anon']);
    }

    public function testCreateAssignsCurrentUserAsOwnerAndIgnoresOwnerIdInput(): void
    {
        $data = $this->apiJson('POST', '/baskets', self::USER, [
            'name' => 'My basket',
            'description' => 'Desc',
            'ownerId' => self::OTHER,
        ]);

        $this->assertSame('basket', $data['@type']);
        $this->assertSame('My basket', $data['name']);
        $this->assertSame(0, $data['assetCount']);
        $this->assertFalse($data['isArchived']);
        $this->assertSame(self::USER, $data['owner']['id']);
        $this->assertSame([
            'edit' => true,
            'share' => true,
            'delete' => true,
            'editPermissions' => true,
        ], $data['capabilities']);

        $basket = self::getEntityManager()->find(Basket::class, $data['id']);
        $this->assertSame(self::USER, $basket->getOwnerId());
        $this->assertSame('Desc', $basket->getDescription());
    }

    public function testCreateRequiresName(): void
    {
        $response = $this->api('POST', '/baskets', self::USER, ['description' => 'No name']);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('name', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testCreateRejectsTooLongName(): void
    {
        $this->assertStatus(422, 'POST', '/baskets', self::USER, ['name' => str_repeat('a', 256)]);
    }

    public function testReadAccessMatrix(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $data = $this->apiJson('GET', $uri, self::USER);
        $this->assertSame('B', $data['name']);
        $this->assertSame(self::USER, $data['owner']['id']);

        // Admin can read any basket (AdminVoter)
        $this->assertStatus(200, 'GET', $uri, self::ADMIN);
        $this->assertStatus(403, 'GET', $uri, self::OTHER);
        $this->assertStatus(401, 'GET', $uri, null);
        $this->assertStatus(404, 'GET', '/baskets/00000000-0000-4000-8000-000000000000', self::USER);
    }

    public function testBasketSharedWithViewPermissionIsReadOnly(): void
    {
        $asset = $this->createReadableAsset(self::OTHER);
        $basket = $this->createBasket(['name' => 'Shared', 'ownerId' => self::USER]);
        $this->grantUserOnObject(self::OTHER, $basket, PermissionInterface::VIEW);
        $uri = '/baskets/'.$basket->getId();

        $data = $this->apiJson('GET', $uri, self::OTHER);
        $this->assertSame('Shared', $data['name']);
        $this->assertSame([
            'edit' => false,
            'share' => false,
            'delete' => false,
            'editPermissions' => false,
        ], $data['capabilities']);

        // Assets can be listed by a granted user
        $this->assertStatus(200, 'GET', $uri.'/assets', self::OTHER);

        $this->assertStatus(403, 'PUT', $uri, self::OTHER, ['name' => 'Hijacked']);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(403, 'POST', $uri.'/assets', self::OTHER, ['assets' => [['id' => $asset->getId()]]]);
        $this->assertStatus(403, 'POST', $uri.'/remove', self::OTHER, ['items' => []]);
        $this->assertStatus(403, 'POST', $uri.'/archive', self::OTHER, []);
        $this->assertStatus(403, 'POST', $uri.'/unarchive', self::OTHER, []);

        self::getEntityManager()->clear();
        $this->assertSame('Shared', self::getEntityManager()->find(Basket::class, $basket->getId())->getName());
    }

    public function testBasketSharedWithEditPermissionCanBeModifiedButNotDeleted(): void
    {
        $asset = $this->createReadableAsset(self::OTHER);
        $basket = $this->createBasket(['name' => 'Shared', 'ownerId' => self::USER]);
        $this->grantUserOnObject(self::OTHER, $basket, PermissionInterface::VIEW | PermissionInterface::EDIT);
        $uri = '/baskets/'.$basket->getId();

        $data = $this->apiJson('GET', $uri, self::OTHER);
        $this->assertTrue($data['capabilities']['edit']);
        $this->assertFalse($data['capabilities']['delete']);

        $data = $this->apiJson('PUT', $uri, self::OTHER, ['name' => 'Renamed by other']);
        $this->assertSame('Renamed by other', $data['name']);

        $data = $this->apiJson('POST', $uri.'/assets', self::OTHER, ['assets' => [['id' => $asset->getId()]]]);
        $this->assertSame(1, $data['assetCount']);

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
    }

    public function testBasketCanBeEditedThroughEditOnlyAclButNotReadWithoutView(): void
    {
        $basket = $this->createBasket(['name' => 'Edit only', 'ownerId' => self::USER]);
        $this->grantUserOnObject(self::OTHER, $basket, PermissionInterface::EDIT);
        $uri = '/baskets/'.$basket->getId();

        $this->assertStatus(403, 'GET', $uri, self::OTHER);
        $this->assertStatus(201, 'POST', $uri.'/archive', self::OTHER, []);
    }

    public function testUpdateIsPartial(): void
    {
        $basket = $this->createBasket(['name' => 'Name', 'description' => 'Initial description', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $data = $this->apiJson('PUT', $uri, self::USER, ['name' => 'New name']);
        $this->assertSame('New name', $data['name']);

        self::getEntityManager()->clear();
        $basket = self::getEntityManager()->find(Basket::class, $basket->getId());
        $this->assertSame('New name', $basket->getName());
        // Omitted fields are left untouched
        $this->assertSame('Initial description', $basket->getDescription());

        $this->apiJson('PUT', $uri, self::USER, ['description' => 'Updated description']);
        self::getEntityManager()->clear();
        $basket = self::getEntityManager()->find(Basket::class, $basket->getId());
        $this->assertSame('New name', $basket->getName());
        $this->assertSame('Updated description', $basket->getDescription());
    }

    public function testUpdateWithBlankNameIsRejected(): void
    {
        $basket = $this->createBasket(['name' => 'Name', 'ownerId' => self::USER]);

        $this->assertStatus(422, 'PUT', '/baskets/'.$basket->getId(), self::USER, ['name' => '']);
    }

    public function testUpdateCannotTransferOwnership(): void
    {
        $basket = $this->createBasket(['name' => 'Name', 'ownerId' => self::USER]);

        $this->apiJson('PUT', '/baskets/'.$basket->getId(), self::USER, ['ownerId' => self::OTHER]);

        self::getEntityManager()->clear();
        $this->assertSame(self::USER, self::getEntityManager()->find(Basket::class, $basket->getId())->getOwnerId());
    }

    public function testDelete(): void
    {
        $basket = $this->createBasket(['name' => 'To delete', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(401, 'DELETE', $uri, null);
        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);
        $this->assertStatus(404, 'DELETE', $uri, self::USER);
    }

    public function testAdminCanDeleteAnyBasket(): void
    {
        $basket = $this->createBasket(['name' => 'To delete', 'ownerId' => self::USER]);

        $this->assertStatus(204, 'DELETE', '/baskets/'.$basket->getId(), self::ADMIN);
    }

    public function testAddAssetsAppendsThemAfterExistingItems(): void
    {
        $a1 = $this->createReadableAsset(self::USER, 'A1');
        $a2 = $this->createReadableAsset(self::USER, 'A2');
        $a3 = $this->createReadableAsset(self::USER, 'A3');
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $data = $this->apiJson('POST', $uri.'/assets', self::USER, [
            'assets' => [['id' => $a1->getId()]],
        ]);
        $this->assertSame($basket->getId(), $data['id']);
        $this->assertSame(1, $data['assetCount']);

        $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $a2->getId()]]]);
        $data = $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $a3->getId()]]]);
        $this->assertSame(3, $data['assetCount']);

        $items = $this->members($this->apiJson('GET', $uri.'/assets', self::USER));
        $this->assertSame(
            [$a1->getId(), $a2->getId(), $a3->getId()],
            array_map(static fn (array $i): string => $i['asset']['id'], $items)
        );
        $positions = array_column($items, 'position');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertCount(3, array_unique($positions));
    }

    public function testAssetsAddedInOneRequestKeepTheRequestedOrder(): void
    {
        $this->markTestIncomplete('BUG: AddToBasketProcessor assigns positions in the order returned by AssetRepository::findByIds(), not in the requested order (the $mapping built for that purpose is unused), see src/Api/Processor/AddToBasketProcessor.php:62-80');

        $assets = [];
        for ($i = 0; $i < 6; ++$i) {
            $assets[] = $this->createReadableAsset(self::USER, 'A'.$i);
        }
        $requested = array_reverse(array_map(static fn (Asset $a): string => $a->getId(), $assets));
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $this->apiJson('POST', $uri.'/assets', self::USER, [
            'assets' => array_map(static fn (string $id): array => ['id' => $id], $requested),
        ]);

        $items = $this->members($this->apiJson('GET', $uri.'/assets', self::USER));
        $this->assertSame($requested, array_map(static fn (array $i): string => $i['asset']['id'], $items));
    }

    public function testAddingTheSameAssetTwiceCreatesTwoItems(): void
    {
        $asset = $this->createReadableAsset(self::USER);
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $asset->getId()]]]);
        $data = $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $asset->getId()]]]);

        // No de-duplication: each basket item is a distinct entry (it may carry its own context/annotations)
        $this->assertSame(2, $data['assetCount']);
        $items = $this->members($this->apiJson('GET', $uri.'/assets', self::USER));
        $this->assertCount(2, $items);
        $this->assertNotSame($items[0]['id'], $items[1]['id']);
    }

    public function testAddUnknownAssetIsSilentlyIgnored(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);

        $data = $this->apiJson('POST', '/baskets/'.$basket->getId().'/assets', self::USER, [
            'assets' => [['id' => '00000000-0000-4000-8000-000000000000']],
        ]);
        $this->assertSame(0, $data['assetCount']);
    }

    public function testAddUnreadableAssetIsForbidden(): void
    {
        // Asset in a workspace USER has no access to
        $foreignAsset = $this->createAsset(['ownerId' => 'someone-else']);
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);

        $this->assertStatus(403, 'POST', '/baskets/'.$basket->getId().'/assets', self::USER, [
            'assets' => [['id' => $foreignAsset->getId()]],
        ]);

        self::getEntityManager()->clear();
        $this->assertSame(0, self::getEntityManager()->getRepository(BasketAsset::class)->count([]));
    }

    public function testAddAssetsValidation(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId().'/assets';

        $this->assertStatus(422, 'POST', $uri, self::USER, []);
        $this->assertStatus(422, 'POST', $uri, self::USER, ['assets' => [['foo' => 'bar']]]);
        $this->assertStatus(401, 'POST', $uri, null, ['assets' => []]);
        // Unknown basket: the security expression is evaluated against a null object
        $this->assertStatus(403, 'POST', '/baskets/00000000-0000-4000-8000-000000000000/assets', self::USER, ['assets' => []]);
    }

    public function testAddToDefaultBasketCreatesItOnTheFly(): void
    {
        $asset = $this->createReadableAsset(self::USER);

        $data = $this->apiJson('POST', '/baskets/default/assets', self::USER, [
            'assets' => [['id' => $asset->getId()]],
        ]);
        $basketId = $data['id'];
        $this->assertSame(1, $data['assetCount']);
        $this->assertSame(self::USER, $data['owner']['id']);

        $em = self::getEntityManager();
        $em->clear();
        $baskets = $em->getRepository(Basket::class)->findBy(['ownerId' => self::USER]);
        $this->assertCount(1, $baskets);
        // The implicit basket has no name
        $this->assertNull($baskets[0]->getName());

        // Second call reuses the same basket
        $data = $this->apiJson('POST', '/baskets/default/assets', self::USER, [
            'assets' => [['id' => $asset->getId()]],
        ]);
        $this->assertSame($basketId, $data['id']);
        $this->assertSame(2, $data['assetCount']);

        $em->clear();
        $this->assertSame(1, $em->getRepository(Basket::class)->count(['ownerId' => self::USER]));
    }

    public function testDefaultBasketIsTheOldestOwnedBasket(): void
    {
        $asset = $this->createReadableAsset(self::USER);
        $this->createBasket(['name' => 'Recent', 'ownerId' => self::USER, 'createdAt' => '2025-01-01 10:00:00']);
        $oldest = $this->createBasket(['name' => 'Oldest', 'ownerId' => self::USER, 'createdAt' => '2020-01-01 10:00:00']);
        // Another user's (older) basket is never picked
        $this->createBasket(['name' => 'Other', 'ownerId' => self::OTHER, 'createdAt' => '2010-01-01 10:00:00']);

        $data = $this->apiJson('POST', '/baskets/default/assets', self::USER, [
            'assets' => [['id' => $asset->getId()]],
        ]);
        $this->assertSame($oldest->getId(), $data['id']);
    }

    public function testDefaultBasketSkipsArchivedBaskets(): void
    {
        $this->markTestIncomplete('BUG: POST /baskets/default/assets picks the oldest basket of the user even when it is archived (hidden from GET /baskets by default), see AddToBasketProcessor::process()');

        $asset = $this->createReadableAsset(self::USER);
        $archived = $this->createBasket(['name' => 'Archived', 'ownerId' => self::USER, 'createdAt' => '2020-01-01 10:00:00']);
        $archived->archive();
        $active = $this->createBasket(['name' => 'Active', 'ownerId' => self::USER, 'createdAt' => '2025-01-01 10:00:00']);
        self::getEntityManager()->flush();

        $data = $this->apiJson('POST', '/baskets/default/assets', self::USER, [
            'assets' => [['id' => $asset->getId()]],
        ]);
        $this->assertSame($active->getId(), $data['id']);
    }

    public function testAddToDefaultBasketRequiresAuthentication(): void
    {
        // Rejected by AddToBasketProcessor::getStrictUser()
        $this->assertStatus(403, 'POST', '/baskets/default/assets', null, ['assets' => []]);
    }

    public function testRemoveItems(): void
    {
        $a1 = $this->createReadableAsset(self::USER, 'A1');
        $a2 = $this->createReadableAsset(self::USER, 'A2');
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $otherBasket = $this->createBasket(['name' => 'Other', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $a1->getId()], ['id' => $a2->getId()]]]);
        $this->apiJson('POST', '/baskets/'.$otherBasket->getId().'/assets', self::USER, ['assets' => [['id' => $a1->getId()]]]);

        $items = $this->members($this->apiJson('GET', $uri.'/assets', self::USER));
        $otherItems = $this->members($this->apiJson('GET', '/baskets/'.$otherBasket->getId().'/assets', self::USER));
        $this->assertCount(2, $items);
        $this->assertCount(1, $otherItems);

        // Items of another basket passed along are ignored
        $data = $this->apiJson('POST', $uri.'/remove', self::USER, [
            'items' => [$items[0]['id'], $otherItems[0]['id']],
        ]);
        $this->assertSame(1, $data['assetCount']);

        $remaining = $this->members($this->apiJson('GET', $uri.'/assets', self::USER));
        $this->assertSame(
            array_values(array_diff([$a1->getId(), $a2->getId()], [$items[0]['asset']['id']])),
            array_map(static fn (array $i): string => $i['asset']['id'], $remaining)
        );
        $this->assertCount(1, $this->members($this->apiJson('GET', '/baskets/'.$otherBasket->getId().'/assets', self::USER)));

        // Removing assets from the basket does not delete the assets
        self::getEntityManager()->clear();
        $this->assertNotNull(self::getEntityManager()->find(Asset::class, $a1->getId()));
    }

    public function testRemoveItemsValidation(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);

        $this->assertStatus(422, 'POST', '/baskets/'.$basket->getId().'/remove', self::USER, []);
        $this->assertStatus(201, 'POST', '/baskets/'.$basket->getId().'/remove', self::USER, ['items' => []]);
    }

    public function testArchiveAndUnarchive(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();

        $this->assertStatus(403, 'POST', $uri.'/archive', self::OTHER, []);
        $this->assertStatus(401, 'POST', $uri.'/archive', null, []);

        $data = $this->apiJson('POST', $uri.'/archive', self::USER, []);
        $this->assertTrue($data['isArchived']);
        $this->assertTrue($this->apiJson('GET', $uri, self::USER)['isArchived']);

        // Archiving twice is idempotent
        $this->assertTrue($this->apiJson('POST', $uri.'/archive', self::USER, [])['isArchived']);

        // An archived basket can still receive assets
        $asset = $this->createReadableAsset(self::USER);
        $this->assertSame(1, $this->apiJson('POST', $uri.'/assets', self::USER, ['assets' => [['id' => $asset->getId()]]])['assetCount']);

        $data = $this->apiJson('POST', $uri.'/unarchive', self::USER, []);
        $this->assertFalse($data['isArchived']);
        $this->assertFalse($this->apiJson('GET', $uri, self::USER)['isArchived']);

        $this->assertStatus(403, 'POST', '/baskets/00000000-0000-4000-8000-000000000000/archive', self::USER, []);
    }

    public function testBasketAssetsListing(): void
    {
        $asset = $this->createReadableAsset(self::USER, 'Listed asset');
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId().'/assets';
        $this->apiJson('POST', '/baskets/'.$basket->getId().'/assets', self::USER, ['assets' => [['id' => $asset->getId()]]]);

        $data = $this->apiJson('GET', $uri, self::USER);
        $items = $this->members($data);
        $this->assertCount(1, $items);
        $this->assertArrayHasKey('id', $items[0]);
        $this->assertArrayHasKey('position', $items[0]);
        $this->assertSame($asset->getId(), $items[0]['asset']['id']);

        $this->assertStatus(200, 'GET', $uri, self::ADMIN);
    }

    public function testListingAssetsOfUnknownBasketIs404(): void
    {
        $this->markTestIncomplete('BUG: GET /baskets/{unknown}/assets answers 500 (InvalidArgumentException) instead of 404: DoctrineUtil::findStrictByRepo() is called without $throw404, see src/Api/Provider/BasketAssetCollectionProvider.php:25');

        $this->assertStatus(404, 'GET', '/baskets/00000000-0000-4000-8000-000000000000/assets', self::USER);
    }

    public function testBasketAssetItemIsNotExposed(): void
    {
        $asset = $this->createReadableAsset(self::USER);
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        $this->apiJson('POST', '/baskets/'.$basket->getId().'/assets', self::USER, ['assets' => [['id' => $asset->getId()]]]);
        $item = $this->members($this->apiJson('GET', '/baskets/'.$basket->getId().'/assets', self::USER))[0];

        // The route only exists for IRI generation
        $this->assertStatus(404, 'GET', '/basket-assets/'.$item['id'], self::USER);
    }

    private function createReadableAsset(string $userId, ?string $title = null): Asset
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => 'ws-owner']);
        $this->addUserOnWorkspace($userId, $workspace->getId());

        return $this->createAsset([
            'name' => $title,
            'workspace' => $workspace,
            'ownerId' => $userId,
        ]);
    }
}
