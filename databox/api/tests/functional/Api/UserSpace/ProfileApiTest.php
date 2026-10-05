<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Profiles (display profiles) and their items.
 * The happy path of POST /profiles + items is covered by Api\ProfileTest.
 */
final class ProfileApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    private const string UNKNOWN_ID = '00000000-0000-4000-8000-000000000000';

    public function testCreateRequiresAuthentication(): void
    {
        $this->assertStatus(401, 'POST', '/profiles', null, ['name' => 'Anon']);
    }

    public function testCreateAssignsCurrentUserAndDefaultsToPrivate(): void
    {
        $data = $this->apiJson('POST', '/profiles', self::USER, [
            'name' => 'Mine',
            'ownerId' => self::OTHER,
            'data' => ['foo' => 'bar'],
        ]);

        $this->assertSame('Mine', $data['name']);
        $this->assertSame(self::USER, $data['owner']['id']);
        $this->assertSame(['foo' => 'bar'], $data['data']);
        $this->assertSame([], $data['items']);
        $this->assertSame([
            'edit' => true,
            'delete' => true,
            'editPermissions' => true,
        ], $data['capabilities']);

        $profile = self::getEntityManager()->find(Profile::class, $data['id']);
        $this->assertSame(self::USER, $profile->getOwnerId());
        $this->assertFalse($profile->isPublic());
    }

    public function testCreateRequiresName(): void
    {
        $this->assertStatus(422, 'POST', '/profiles', self::USER, ['description' => 'x']);
        $this->assertStatus(422, 'POST', '/profiles', self::USER, ['name' => str_repeat('a', 256)]);
    }

    public function testListShowsOwnPublicAndGrantedProfilesOrderedByName(): void
    {
        $mine = $this->createProfile('B mine', self::USER);
        $public = $this->createProfile('A public', self::OTHER, public: true);
        $granted = $this->createProfile('C granted', self::OTHER);
        $this->grantUserOnObject(self::USER, $granted, PermissionInterface::VIEW);
        $this->createProfile('D private of other', self::OTHER);

        $data = $this->apiJson('GET', '/profiles', self::USER);
        $this->assertSame(
            [$public->getId(), $mine->getId(), $granted->getId()],
            $this->memberIds($data)
        );

        $byId = array_column($this->members($data), null, 'id');
        $this->assertTrue($byId[$mine->getId()]['capabilities']['edit']);
        $this->assertFalse($byId[$public->getId()]['capabilities']['edit']);
        $this->assertFalse($byId[$granted->getId()]['capabilities']['edit']);
        // Items are only exposed on the item operation
        $this->assertArrayNotHasKey('items', $byId[$mine->getId()]);
    }

    public function testAnonymousListsOnlyPublicProfiles(): void
    {
        $this->createProfile('Private', self::USER);
        $public = $this->createProfile('Public', self::USER, public: true);

        $this->assertSame([$public->getId()], $this->memberIds($this->apiJson('GET', '/profiles', null)));
    }

    public function testAdminListIsNotWidened(): void
    {
        // The list is filtered by ownership/ACL in SQL, even for admins
        $this->createProfile('Private', self::USER);

        $this->assertSame([], $this->memberIds($this->apiJson('GET', '/profiles', self::ADMIN)));
    }

    public function testOwnerAndGranteesCanReadProfile(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $uri = '/profiles/'.$profile->getId();

        $data = $this->apiJson('GET', $uri, self::USER);
        $this->assertSame('P', $data['name']);
        $this->assertSame([], $data['items']);

        $this->grantUserOnObject(self::OTHER, $profile, PermissionInterface::VIEW);
        $data = $this->apiJson('GET', $uri, self::OTHER);
        $this->assertFalse($data['capabilities']['edit']);

        $this->assertStatus(404, 'GET', '/profiles/'.self::UNKNOWN_ID, self::USER);
    }

    public function testPublicProfileIsReadableByAnyone(): void
    {
        $profile = $this->createProfile('Public', self::USER, public: true);

        $this->assertStatus(200, 'GET', '/profiles/'.$profile->getId(), self::OTHER);
        $this->assertStatus(200, 'GET', '/profiles/'.$profile->getId(), null);
    }

    public function testPrivateProfileIsNotReadableByOthers(): void
    {
        $profile = $this->createProfile('Private', self::USER);

        $this->assertStatus(403, 'GET', '/profiles/'.$profile->getId(), self::OTHER);
        $this->assertStatus(401, 'GET', '/profiles/'.$profile->getId(), null);
        $this->assertStatus(403, 'GET', '/profiles/'.$profile->getId().'/items', self::OTHER);
    }

    public function testItemsOfUnreadableDefinitionsAreHidden(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $readable = $this->createDefinitionIn($this->workspaceOf(self::USER), 'Readable');
        $foreign = $this->createDefinitionIn($this->createOtherWorkspace('stranger', 'foreign'), 'Foreign');
        $this->addItem($profile, ProfileItem::TYPE_ATTR_DEF, ProfileItem::SECTION_ATTRIBUTES, definition: $readable, position: 0);
        $this->addItem($profile, ProfileItem::TYPE_ATTR_DEF, ProfileItem::SECTION_ATTRIBUTES, definition: $foreign, position: 1);
        $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'createdAt', position: 2);

        $items = $this->apiJson('GET', '/profiles/'.$profile->getId(), self::USER)['items'];
        $this->assertSame([$readable->getId(), null], array_map(static fn (array $i): ?string => $i['definition'] ?? null, $items));
        $this->assertSame([null, 'createdAt'], array_map(static fn (array $i): ?string => $i['key'] ?? null, $items));
    }

    public function testUpdate(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $uri = '/profiles/'.$profile->getId();

        $data = $this->apiJson('PUT', $uri, self::USER, ['public' => true, 'data' => ['k' => 1]]);
        $this->assertSame('P', $data['name']);
        $this->assertSame(['k' => 1], $data['data']);

        self::getEntityManager()->clear();
        $profile = self::getEntityManager()->find(Profile::class, $profile->getId());
        $this->assertTrue($profile->isPublic());
        $this->assertSame('P', $profile->getName());

        $this->assertStatus(422, 'PUT', $uri, self::USER, ['name' => '']);
    }

    public function testUpdateAccessMatrix(): void
    {
        $profile = $this->createProfile('P', self::USER, public: true);
        $uri = '/profiles/'.$profile->getId();

        // Public does not mean editable
        $this->assertStatus(403, 'PUT', $uri, self::OTHER, ['name' => 'Hijack']);
        $this->assertStatus(401, 'PUT', $uri, null, ['name' => 'Hijack']);
        $this->assertStatus(200, 'PUT', $uri, self::ADMIN, ['name' => 'By admin']);

        $this->grantUserOnObject(self::OTHER, $profile, PermissionInterface::EDIT);
        $this->assertSame('By other', $this->apiJson('PUT', $uri, self::OTHER, ['name' => 'By other'])['name']);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
    }

    public function testDelete(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'createdAt');
        $uri = '/profiles/'.$profile->getId();

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(401, 'DELETE', $uri, null);
        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);

        $this->grantUserOnObject(self::OTHER, $other = $this->createProfile('Other', self::USER), PermissionInterface::DELETE);
        $this->assertStatus(204, 'DELETE', '/profiles/'.$other->getId(), self::OTHER);
    }

    public function testAddToDefaultProfileCreatesItOnTheFly(): void
    {
        $em = self::getEntityManager();
        $this->assertSame(0, $em->getRepository(Profile::class)->count(['ownerId' => self::USER]));

        $data = $this->apiJson('POST', '/profiles/default/items', self::USER, [
            'items' => [
                ['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'createdAt', 'section' => ProfileItem::SECTION_ATTRIBUTES],
            ],
        ]);
        $this->assertSame(self::USER, $data['owner']['id']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('createdAt', $data['items'][0]['key']);

        $em->clear();
        $this->assertSame(1, $em->getRepository(Profile::class)->count(['ownerId' => self::USER]));

        $again = $this->apiJson('POST', '/profiles/default/items', self::USER, ['items' => []]);
        $this->assertSame($data['id'], $again['id']);
    }

    public function testAddToDefaultProfileRequiresAuthentication(): void
    {
        // Rejected by AddToProfileProcessor::getStrictUser()
        $this->assertStatus(403, 'POST', '/profiles/default/items', null, ['items' => []]);
    }

    public function testAddItemsAccessControl(): void
    {
        $profile = $this->createProfile('P', self::USER, public: true);
        $uri = '/profiles/'.$profile->getId().'/items';
        $item = ['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'createdAt', 'section' => ProfileItem::SECTION_ATTRIBUTES];

        $this->assertStatus(403, 'POST', $uri, self::OTHER, ['items' => [$item]]);
        $this->assertStatus(403, 'POST', '/profiles/'.$profile->getId().'/remove', self::OTHER, ['items' => []]);
        $this->assertStatus(403, 'POST', '/profiles/'.self::UNKNOWN_ID.'/items', self::USER, ['items' => [$item]]);

        $this->grantUserOnObject(self::OTHER, $profile, PermissionInterface::EDIT);
        $this->assertCount(1, $this->apiJson('POST', $uri, self::OTHER, ['items' => [$item]])['items']);
    }

    public function testAddItemsAppendsAndDeduplicatesDefinitionsPerSection(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $def = $this->createDefinitionIn($this->workspaceOf(self::USER), 'Title');
        $uri = '/profiles/'.$profile->getId().'/items';

        $data = $this->apiJson('POST', $uri, self::USER, ['items' => [
            ['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => $def->getId(), 'section' => ProfileItem::SECTION_ATTRIBUTES],
            ['type' => ProfileItem::TYPE_DIVIDER, 'key' => 'Section A', 'section' => ProfileItem::SECTION_ATTRIBUTES],
            ['type' => ProfileItem::TYPE_SPACER, 'section' => ProfileItem::SECTION_ATTRIBUTES],
        ]]);
        $this->assertSame(
            [ProfileItem::TYPE_ATTR_DEF, ProfileItem::TYPE_DIVIDER, ProfileItem::TYPE_SPACER],
            array_column($data['items'], 'type')
        );
        // Attribute & non-grid items display empty values by default
        $this->assertTrue($data['items'][0]['displayEmpty']);

        // Same definition in another section is accepted, twice in the same section is ignored
        $data = $this->apiJson('POST', $uri, self::USER, ['items' => [
            ['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => $def->getId(), 'section' => ProfileItem::SECTION_ATTRIBUTES],
            ['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => $def->getId(), 'section' => ProfileItem::SECTION_FACETS],
        ]]);
        $this->assertCount(4, $data['items']);
        $this->assertSame(ProfileItem::SECTION_FACETS, $data['items'][3]['section']);
        $this->assertSame($def->getId(), $data['items'][3]['definition']);
    }

    public function testAddGridItem(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $uri = '/profiles/'.$profile->getId().'/items';

        $data = $this->apiJson('POST', $uri, self::USER, ['items' => [[
            'type' => ProfileItem::TYPE_BUILT_IN,
            'key' => 'name',
            'section' => ProfileItem::SECTION_GRID,
            'placement' => ['region' => 'below', 'anchor' => 'l'],
            'size' => 'small',
            'color' => 'primary',
            'showLabel' => true,
        ]]]);

        $item = $data['items'][0];
        $this->assertSame(['region' => 'below', 'anchor' => 'l'], $item['placement']);
        $this->assertSame('small', $item['size']);
        $this->assertSame('primary', $item['color']);
        $this->assertTrue($item['showLabel']);
        // Grid items hide empty values by default
        $this->assertFalse($item['displayEmpty']);
    }

    /**
     * @dataProvider getInvalidItems
     */
    public function testAddItemsValidation(array $item): void
    {
        $profile = $this->createProfile('P', self::USER);

        $this->assertStatus(422, 'POST', '/profiles/'.$profile->getId().'/items', self::USER, ['items' => [$item]]);
    }

    public static function getInvalidItems(): array
    {
        return [
            'missing section' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name']],
            'missing type' => [['key' => 'name', 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'unknown section' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name', 'section' => 42]],
            'unknown type' => [['type' => 42, 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'definition without definition' => [['type' => ProfileItem::TYPE_ATTR_DEF, 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'definition with key' => [['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => self::UNKNOWN_ID, 'key' => 'k', 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'built-in without key' => [['type' => ProfileItem::TYPE_BUILT_IN, 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'spacer with key' => [['type' => ProfileItem::TYPE_SPACER, 'key' => 'k', 'section' => ProfileItem::SECTION_ATTRIBUTES]],
            'grid without placement' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name', 'section' => ProfileItem::SECTION_GRID]],
            'grid invalid region' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name', 'section' => ProfileItem::SECTION_GRID, 'placement' => ['region' => 'nowhere', 'anchor' => 'l']]],
            'grid reserved anchor' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name', 'section' => ProfileItem::SECTION_GRID, 'placement' => ['region' => 'over', 'anchor' => 'tl']]],
            'invalid size' => [['type' => ProfileItem::TYPE_BUILT_IN, 'key' => 'name', 'section' => ProfileItem::SECTION_ATTRIBUTES, 'size' => 'huge']],
        ];
    }

    public function testAddItemsWithoutItemsIsRejected(): void
    {
        $profile = $this->createProfile('P', self::USER);

        $this->assertStatus(422, 'POST', '/profiles/'.$profile->getId().'/items', self::USER, []);
    }

    public function testAddUnreadableDefinitionIsForbidden(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $foreign = $this->createDefinitionIn($this->createOtherWorkspace('stranger', 'foreign'), 'Foreign');

        $this->assertStatus(403, 'POST', '/profiles/'.$profile->getId().'/items', self::USER, ['items' => [
            ['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => $foreign->getId(), 'section' => ProfileItem::SECTION_ATTRIBUTES],
        ]]);
    }

    public function testAddUnknownDefinitionIsRejected(): void
    {
        $this->markTestIncomplete('BUG: an unknown definition id answers 500 (InvalidArgumentException from DoctrineUtil::findStrictByRepo()) instead of a 4xx, see src/Api/Processor/AddToProfileProcessor.php:84');

        $profile = $this->createProfile('P', self::USER);
        $response = $this->api('POST', '/profiles/'.$profile->getId().'/items', self::USER, ['items' => [
            ['type' => ProfileItem::TYPE_ATTR_DEF, 'definition' => self::UNKNOWN_ID, 'section' => ProfileItem::SECTION_ATTRIBUTES],
        ]]);
        $this->assertContains($response->getStatusCode(), [400, 404, 422]);
    }

    public function testDividerIsNotAllowedInFacets(): void
    {
        $this->markTestIncomplete('BUG: a divider/spacer in the facets section is rejected with a raw \InvalidArgumentException, which answers 500 instead of 400/422, see src/Api/Processor/AddToProfileProcessor.php:79');

        $profile = $this->createProfile('P', self::USER);
        $response = $this->api('POST', '/profiles/'.$profile->getId().'/items', self::USER, ['items' => [
            ['type' => ProfileItem::TYPE_DIVIDER, 'key' => 'D', 'section' => ProfileItem::SECTION_FACETS],
        ]]);
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testRemoveItems(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $other = $this->createProfile('Other', self::USER);
        $i1 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'a', position: 0);
        $i2 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'b', position: 1);
        $foreignItem = $this->addItem($other, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'c');

        $data = $this->apiJson('POST', '/profiles/'.$profile->getId().'/remove', self::USER, [
            'items' => [$i1->getId(), $foreignItem->getId()],
        ]);
        $this->assertSame([$i2->getId()], array_column($data['items'], 'id'));

        // Items of another profile are left untouched
        $this->assertCount(1, $this->apiJson('GET', '/profiles/'.$other->getId(), self::USER)['items']);

        $this->assertStatus(422, 'POST', '/profiles/'.$profile->getId().'/remove', self::USER, []);
    }

    public function testListItems(): void
    {
        $this->markTestIncomplete('BUG: GET /profiles/{id}/items always returns an empty collection: the resource-level "itemId" Link (meant for the PUT item operation) also applies to the GetCollection, see src/Entity/Profile/ProfileItem.php:33-36');

        $profile = $this->createProfile('P', self::USER);
        $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'second', position: 5);
        $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_FACETS, key: 'first', position: 1);
        $this->addItem($this->createProfile('Other', self::USER), ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'foreign');

        $data = $this->apiJson('GET', '/profiles/'.$profile->getId().'/items', self::USER);
        $this->assertSame(['first', 'second'], array_column($this->members($data), 'key'));
        $this->assertSame([ProfileItem::SECTION_FACETS, ProfileItem::SECTION_ATTRIBUTES], array_column($this->members($data), 'section'));
    }

    public function testProfileItemIsNotExposedAsItem(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $item = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'k');

        $this->assertStatus(404, 'GET', '/profile-items/'.$item->getId(), self::USER);
    }

    public function testUpdateItem(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $item = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_GRID, key: 'name', placement: ['region' => 'below', 'anchor' => 'l']);
        $uri = '/profiles/'.$profile->getId().'/items/'.$item->getId();

        $data = $this->apiJson('PUT', $uri, self::USER, [
            'type' => ProfileItem::TYPE_BUILT_IN,
            'section' => ProfileItem::SECTION_GRID,
            'key' => 'name',
            'placement' => ['region' => 'over', 'anchor' => 'cc', 'order' => 2],
            'variant' => 'chip',
            'showIcon' => false,
        ]);
        $this->assertSame($item->getId(), $data['id']);
        $this->assertSame(['region' => 'over', 'anchor' => 'cc', 'order' => 2], $data['placement']);
        $this->assertSame('chip', $data['variant']);
        $this->assertFalse($data['showIcon']);

        // Empty string clears the color
        $this->apiJson('PUT', $uri, self::USER, [
            'type' => ProfileItem::TYPE_BUILT_IN,
            'section' => ProfileItem::SECTION_GRID,
            'key' => 'name',
            'placement' => ['region' => 'over', 'anchor' => 'cc'],
            'color' => 'primary',
        ]);
        $data = $this->apiJson('PUT', $uri, self::USER, [
            'type' => ProfileItem::TYPE_BUILT_IN,
            'section' => ProfileItem::SECTION_GRID,
            'key' => 'name',
            'placement' => ['region' => 'over', 'anchor' => 'cc'],
            'color' => '',
        ]);
        $this->assertNull($data['color'] ?? null);
    }

    public function testUpdateItemAccessAndLookup(): void
    {
        $profile = $this->createProfile('P', self::USER, public: true);
        $item = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'k');
        $other = $this->createProfile('Other', self::USER);
        $payload = ['type' => ProfileItem::TYPE_BUILT_IN, 'section' => ProfileItem::SECTION_ATTRIBUTES, 'key' => 'renamed'];

        $this->assertStatus(403, 'PUT', '/profiles/'.$profile->getId().'/items/'.$item->getId(), self::OTHER, $payload);
        // The item must belong to the profile of the URL
        $this->assertStatus(404, 'PUT', '/profiles/'.$other->getId().'/items/'.$item->getId(), self::USER, $payload);
        // ...and to the section given in the payload
        $this->assertStatus(404, 'PUT', '/profiles/'.$profile->getId().'/items/'.$item->getId(), self::USER, array_merge($payload, ['section' => ProfileItem::SECTION_FACETS]));
        $this->assertStatus(422, 'PUT', '/profiles/'.$profile->getId().'/items/'.$item->getId(), self::USER, ['key' => 'x']);

        $data = $this->apiJson('PUT', '/profiles/'.$profile->getId().'/items/'.$item->getId(), self::USER, $payload);
        $this->assertSame('renamed', $data['key']);
    }

    public function testSortItems(): void
    {
        $profile = $this->createProfile('P', self::USER);
        $i1 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'a', position: 0);
        $i2 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'b', position: 1);
        $i3 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'c', position: 2);
        $uri = '/profiles/'.$profile->getId().'/sort';

        $response = static::createClient()->request('POST', $uri, [
            'headers' => self::authHeaders(self::USER),
            'json' => [$i3->getId(), $i1->getId(), $i2->getId()],
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $items = $this->apiJson('GET', '/profiles/'.$profile->getId(), self::USER)['items'];
        $this->assertSame(['c', 'a', 'b'], array_column($items, 'key'));

        // Empty list is a no-op
        $response = static::createClient()->request('POST', $uri, [
            'headers' => self::authHeaders(self::USER),
            'json' => [],
        ]);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSortItemsAccessControl(): void
    {
        $profile = $this->createProfile('P', self::USER, public: true);
        $i1 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'a', position: 0);
        $i2 = $this->addItem($profile, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'b', position: 1);
        $foreign = $this->createProfile('Other', self::OTHER);
        $foreignItem = $this->addItem($foreign, ProfileItem::TYPE_BUILT_IN, ProfileItem::SECTION_ATTRIBUTES, key: 'z', position: 0);

        $response = static::createClient()->request('POST', '/profiles/'.$profile->getId().'/sort', [
            'headers' => self::authHeaders(self::OTHER),
            'json' => [$i2->getId(), $i1->getId()],
        ]);
        $this->assertSame(403, $response->getStatusCode());

        $response = static::createClient()->request('POST', '/profiles/'.$profile->getId().'/sort', [
            'headers' => self::authHeaders(self::USER),
            'json' => [self::UNKNOWN_ID],
        ]);
        $this->assertSame(404, $response->getStatusCode());

        // Ids of another profile's items are not re-positioned
        $response = static::createClient()->request('POST', '/profiles/'.$profile->getId().'/sort', [
            'headers' => self::authHeaders(self::USER),
            'json' => [$i2->getId(), $foreignItem->getId(), $i1->getId()],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        self::getEntityManager()->clear();
        $this->assertSame(0, self::getEntityManager()->find(ProfileItem::class, $foreignItem->getId())->getPosition());
        $this->assertSame(['b', 'a'], array_column($this->apiJson('GET', '/profiles/'.$profile->getId(), self::USER)['items'], 'key'));
    }

    private function createProfile(string $name, string $ownerId, bool $public = false): Profile
    {
        $em = self::getEntityManager();
        $profile = new Profile();
        $profile->setName($name);
        $profile->setOwnerId($ownerId);
        $profile->setPublic($public);
        $em->persist($profile);
        $em->flush();

        return $profile;
    }

    private function addItem(
        Profile $profile,
        int $type,
        int $section,
        ?AttributeDefinition $definition = null,
        ?string $key = null,
        int $position = 0,
        ?array $placement = null,
    ): ProfileItem {
        $em = self::getEntityManager();
        $item = new ProfileItem();
        $item->setProfile($em->find(Profile::class, $profile->getId()));
        $item->setType($type);
        $item->setSection($section);
        $item->setDefinition($definition ? $em->find(AttributeDefinition::class, $definition->getId()) : null);
        $item->setKey($key);
        $item->setPosition($position);
        $item->setPlacement($placement);
        $em->persist($item);
        $em->flush();

        return $item;
    }

    private function workspaceOf(string $userId): Workspace
    {
        return $this->getOrCreateDefaultWorkspace(['ownerId' => $userId]);
    }

    private function createDefinitionIn(Workspace $workspace, string $name): AttributeDefinition
    {
        return $this->createAttributeDefinition([
            'name' => $name,
            'workspace' => $workspace,
            'policy' => $this->createAttributePolicy([
                'name' => $name.' policy',
                'workspace' => $workspace,
                'public' => true,
            ]),
        ]);
    }
}
