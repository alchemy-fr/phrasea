<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Basket\Basket;
use App\Entity\Core\Workspace;
use App\Entity\Profile\Profile;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Instance capabilities (App\Security\InstancePermission): granted by a metadata of
 * an ACE set on a whole object type (no object id), to admins only by default.
 */
final class InstancePermissionTest extends AbstractDataboxTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string GROUP = KeycloakClientTestMock::GROUP_UID;

    /**
     * @param string[]|null $groups the groups of the token
     */
    private function request(string $method, string $uri, ?string $userId, ?array $json = null, ?array $groups = null, array $query = []): ResponseInterface
    {
        $options = ['headers' => []];
        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId, groups: $groups);
        }
        if ('PATCH' === $method) {
            $options['headers']['Content-Type'] = 'application/merge-patch+json';
        }
        if (null !== $json) {
            $options['json'] = $json;
        }
        if (!empty($query)) {
            $options['query'] = $query;
        }

        return static::createClient()->request($method, $uri, $options);
    }

    private function assertRequestStatus(int $expected, string $method, string $uri, ?string $userId, ?array $json = null, ?array $groups = null): ResponseInterface
    {
        $response = $this->request($method, $uri, $userId, $json, $groups);
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            sprintf('%s %s as %s: unexpected status. Body: %s', $method, $uri, $userId ?? 'anonymous', $response->getContent(false))
        );

        return $response;
    }

    private function grantType(string $objectType, int $capability, string $userId = self::USER, int $userType = AccessControlEntryInterface::TYPE_USER_VALUE): void
    {
        self::getPermissionManager()->updateOrCreateAce($userType, $userId, $objectType, null, 0, [$capability]);
    }

    private function getCapabilities(string $userId, ?array $groups = null): array
    {
        return $this->assertRequestStatus(200, 'GET', '/capabilities', $userId, groups: $groups)->toArray();
    }

    public function testNoCapabilityByDefault(): void
    {
        $this->assertSame([
            'createWorkspace' => false,
            'createProfile' => false,
            'shareBasket' => false,
            'publishSavedSearch' => false,
        ], $this->getCapabilities(self::USER));
    }

    public function testAdminHasEveryCapability(): void
    {
        $this->assertSame([
            'createWorkspace' => true,
            'createProfile' => true,
            'shareBasket' => true,
            'publishSavedSearch' => true,
        ], $this->getCapabilities(self::ADMIN));
    }

    public function testCapabilitiesRequireAuthentication(): void
    {
        $this->assertRequestStatus(401, 'GET', '/capabilities', null);
    }

    public static function getCapabilityGrants(): iterable
    {
        yield 'create workspace' => [Workspace::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_WORKSPACE, 'createWorkspace'];
        yield 'create profile' => [Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_PROFILE, 'createProfile'];
        yield 'share basket' => [Basket::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_SHARE_BASKET, 'shareBasket'];
        yield 'publish saved search' => [SavedSearch::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_PUBLISH_SAVED_SEARCH, 'publishSavedSearch'];
    }

    #[DataProvider('getCapabilityGrants')]
    public function testCapabilitiesReflectTheTypeAces(string $objectType, int $capabilityMetadata, string $capability): void
    {
        $this->grantType($objectType, $capabilityMetadata);

        $capabilities = $this->getCapabilities(self::USER);
        $this->assertTrue($capabilities[$capability]);
        $this->assertSame([$capability], array_keys(array_filter($capabilities)));

        // Another user is not granted
        $this->assertFalse($this->getCapabilities(self::OTHER)[$capability]);
    }

    #[DataProvider('getCapabilityGrants')]
    public function testCapabilitiesGrantedToAGroup(string $objectType, int $capabilityMetadata, string $capability): void
    {
        $this->grantType($objectType, $capabilityMetadata, self::GROUP, AccessControlEntryInterface::TYPE_GROUP_VALUE);

        $this->assertTrue($this->getCapabilities(self::OTHER, [self::GROUP])[$capability]);
        $this->assertFalse($this->getCapabilities(self::OTHER, [])[$capability]);
    }

    #[DataProvider('getCapabilityGrants')]
    public function testCapabilitiesGrantedToAllUsers(string $objectType, int $capabilityMetadata, string $capability): void
    {
        $this->grantType($objectType, $capabilityMetadata, AccessControlEntryInterface::USER_WILDCARD);

        $this->assertTrue($this->getCapabilities(self::OTHER)[$capability]);
    }

    public function testAnObjectAceIsNoCapability(): void
    {
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            self::USER,
            Basket::OBJECT_TYPE,
            $basket->getId(),
            PermissionInterface::SHARE,
            [DataboxExtraPermissionInterface::PERM_SHARE_BASKET],
        );

        $this->assertFalse($this->getCapabilities(self::USER)['shareBasket']);
    }

    public function testATypeBitIsNoCapability(): void
    {
        $basket = $this->createBasket(['name' => 'Other', 'ownerId' => self::OTHER]);
        self::getPermissionManager()->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, self::USER, Basket::OBJECT_TYPE, null, PermissionInterface::VIEW | PermissionInterface::SHARE);

        $this->assertFalse($this->getCapabilities(self::USER)['shareBasket']);
        // The bits of a type ACE grant the permission on every object of the type
        $capabilities = $this->assertRequestStatus(200, 'GET', '/baskets/'.$basket->getId(), self::USER)->toArray()['capabilities'];
        $this->assertTrue($capabilities['share']);
    }

    public function testProfileCreation(): void
    {
        $this->assertRequestStatus(403, 'POST', '/profiles', self::USER, ['name' => 'Mine']);
        // The default profile is not created implicitly either
        $this->assertRequestStatus(403, 'POST', '/profiles/default/items', self::USER, ['items' => []]);
        $this->assertSame(0, self::getEntityManager()->getRepository(Profile::class)->count([]));

        $this->assertRequestStatus(201, 'POST', '/profiles', self::ADMIN, ['name' => 'Admin']);

        $this->grantType(Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_PROFILE, self::GROUP, AccessControlEntryInterface::TYPE_GROUP_VALUE);
        $this->assertRequestStatus(403, 'POST', '/profiles', self::USER, ['name' => 'Mine']);
        $data = $this->assertRequestStatus(201, 'POST', '/profiles', self::USER, ['name' => 'Mine'], [self::GROUP])->toArray();
        $this->assertSame(self::USER, $data['owner']['id']);
        $this->assertRequestStatus(201, 'POST', '/profiles/default/items', self::OTHER, ['items' => []], [self::GROUP]);
    }

    public function testProfileCreationDoesNotGrantTheProfilesOfOthers(): void
    {
        $this->grantType(Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_PROFILE);
        $em = self::getEntityManager();
        $profile = new Profile();
        $profile->setName('Other');
        $profile->setOwnerId(self::OTHER);
        $em->persist($profile);
        $em->flush();

        $this->assertRequestStatus(403, 'GET', '/profiles/'.$profile->getId(), self::USER);
        $this->assertRequestStatus(403, 'PATCH', '/profiles/'.$profile->getId(), self::USER, ['name' => 'Hijack']);
    }

    public function testWorkspaceCreation(): void
    {
        $this->assertRequestStatus(403, 'POST', '/workspaces', self::USER, ['name' => 'Nope', 'slug' => 'nope']);

        $this->grantType(Workspace::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_WORKSPACE);
        $data = $this->assertRequestStatus(201, 'POST', '/workspaces', self::USER, [
            'name' => 'Mine',
            'slug' => 'mine',
            // Only admins choose the owner
            'ownerId' => self::OTHER,
        ])->toArray();

        $this->assertSame(self::USER, $data['ownerId']);
        $this->assertSame(self::USER, self::getEntityManager()->find(Workspace::class, $data['id'])->getOwnerId());

        // The creator manages its workspace
        $capabilities = $this->assertRequestStatus(200, 'GET', '/workspaces/'.$data['id'], self::USER)->toArray()['capabilities'];
        $this->assertTrue($capabilities['edit']);
        $this->assertTrue($capabilities['createCollection']);
        $this->assertTrue($capabilities['editPermissions']);

        // Another user is not granted
        $this->assertRequestStatus(403, 'POST', '/workspaces', self::OTHER, ['name' => 'Nope', 'slug' => 'nope']);
    }

    public function testWorkspaceCreationDoesNotGrantTheCollectionCreationInOtherWorkspaces(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OTHER]);
        $this->addUserOnWorkspace(self::USER, $workspace->getId());
        $this->grantType(Workspace::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_WORKSPACE);

        $capabilities = $this->assertRequestStatus(200, 'GET', '/workspaces/'.$workspace->getId(), self::USER)->toArray()['capabilities'];
        $this->assertFalse($capabilities['createCollection']);
        $this->assertFalse($capabilities['edit']);
        $this->assertRequestStatus(403, 'POST', '/collections', self::USER, [
            'name' => 'Intruder',
            'workspace' => '/workspaces/'.$workspace->getId(),
        ]);

        // CREATE on the workspace itself still grants it
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | PermissionInterface::CREATE);
        $capabilities = $this->assertRequestStatus(200, 'GET', '/workspaces/'.$workspace->getId(), self::USER)->toArray()['capabilities'];
        $this->assertTrue($capabilities['createCollection']);
    }

    public function testBasketSharing(): void
    {
        $basket = $this->createBasket(['name' => 'Mine', 'ownerId' => self::USER]);
        $uri = '/baskets/'.$basket->getId();
        $ace = [
            'objectType' => Basket::OBJECT_TYPE,
            'objectId' => $basket->getId(),
            'userType' => 'user',
            'userId' => self::OTHER,
            'mask' => PermissionInterface::VIEW,
        ];

        $capabilities = $this->assertRequestStatus(200, 'GET', $uri, self::USER)->toArray()['capabilities'];
        // The owner still edits and deletes its basket
        $this->assertTrue($capabilities['edit']);
        $this->assertTrue($capabilities['delete']);
        $this->assertFalse($capabilities['share']);
        $this->assertFalse($capabilities['editPermissions']);
        $this->assertRequestStatus(403, 'PUT', '/permissions/ace', self::USER, $ace);
        $this->assertRequestStatus(403, 'GET', '/permissions/aces?objectType=basket&objectId='.$basket->getId(), self::USER);

        $this->grantType(Basket::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_SHARE_BASKET);
        $capabilities = $this->assertRequestStatus(200, 'GET', $uri, self::USER)->toArray()['capabilities'];
        $this->assertTrue($capabilities['share']);
        $this->assertTrue($capabilities['editPermissions']);
        $this->assertRequestStatus(200, 'PUT', '/permissions/ace', self::USER, $ace);
        $this->assertRequestStatus(200, 'GET', $uri, self::OTHER);
    }

    public function testBasketSharingDoesNotGrantTheBasketsOfOthers(): void
    {
        $basket = $this->createBasket(['name' => 'Other', 'ownerId' => self::OTHER]);
        $this->grantUserOnObject(self::USER, $basket, PermissionInterface::VIEW);
        $this->grantType(Basket::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_SHARE_BASKET);

        $capabilities = $this->assertRequestStatus(200, 'GET', '/baskets/'.$basket->getId(), self::USER)->toArray()['capabilities'];
        $this->assertFalse($capabilities['share']);
        $this->assertFalse($capabilities['editPermissions']);
        $this->assertRequestStatus(403, 'PUT', '/permissions/ace', self::USER, [
            'objectType' => Basket::OBJECT_TYPE,
            'objectId' => $basket->getId(),
            'userType' => 'user',
            'userId' => self::USER,
            'mask' => PermissionInterface::OWNER,
        ]);

        // SHARE on the basket itself still grants the sharing
        $this->grantUserOnObject(self::USER, $basket, PermissionInterface::VIEW | PermissionInterface::SHARE);
        $capabilities = $this->assertRequestStatus(200, 'GET', '/baskets/'.$basket->getId(), self::USER)->toArray()['capabilities'];
        $this->assertTrue($capabilities['share']);
        $this->assertFalse($capabilities['editPermissions']);
    }

    public function testSavedSearchPublication(): void
    {
        $payload = ['name' => 'S', 'data' => ['query' => 'cats']];

        $this->assertRequestStatus(403, 'POST', '/saved-searches', self::USER, $payload + ['privacy' => SavedSearchPrivacyEnum::Public->value]);
        $this->assertRequestStatus(403, 'POST', '/saved-searches', self::USER, $payload + ['privacy' => SavedSearchPrivacyEnum::Private->value]);
        $this->assertSame(0, self::getEntityManager()->getRepository(SavedSearch::class)->count([]));

        // Secret needs no capability
        $data = $this->assertRequestStatus(201, 'POST', '/saved-searches', self::USER, $payload + ['privacy' => SavedSearchPrivacyEnum::Secret->value])->toArray();
        $this->assertFalse($data['capabilities']['publish']);
        $this->assertFalse($data['capabilities']['editPermissions']);
        $this->assertTrue($data['capabilities']['edit']);
        $uri = '/saved-searches/'.$data['id'];
        $this->assertRequestStatus(403, 'PATCH', $uri, self::USER, ['privacy' => SavedSearchPrivacyEnum::Public->value]);
        $this->assertSame(SavedSearchPrivacyEnum::Secret, $this->findSavedSearch($data['id'])->getPrivacy());
        $this->assertRequestStatus(403, 'PUT', '/permissions/ace', self::USER, [
            'objectType' => SavedSearch::OBJECT_TYPE,
            'objectId' => $data['id'],
            'userType' => 'user',
            'userId' => self::OTHER,
            'mask' => PermissionInterface::VIEW,
        ]);

        $this->grantType(SavedSearch::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_PUBLISH_SAVED_SEARCH);
        $data = $this->assertRequestStatus(200, 'PATCH', $uri, self::USER, ['privacy' => SavedSearchPrivacyEnum::Public->value])->toArray();
        $this->assertSame(SavedSearchPrivacyEnum::Public->value, $data['privacy']);
        $this->assertTrue($data['capabilities']['publish']);
        $this->assertTrue($data['capabilities']['editPermissions']);
        $this->assertRequestStatus(201, 'POST', '/saved-searches', self::USER, $payload + ['privacy' => SavedSearchPrivacyEnum::Private->value]);
    }

    public function testAPublishedSearchStaysEditableWithoutTheCapability(): void
    {
        $em = self::getEntityManager();
        $search = new SavedSearch();
        $search->setName('Public');
        $search->setOwnerId(self::USER);
        $search->setPrivacy(SavedSearchPrivacyEnum::Public);
        $em->persist($search);
        $em->flush();
        $uri = '/saved-searches/'.$search->getId();

        $data = $this->assertRequestStatus(200, 'PATCH', $uri, self::USER, [
            'name' => 'Renamed',
            'privacy' => SavedSearchPrivacyEnum::Public->value,
        ])->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertSame(SavedSearchPrivacyEnum::Public->value, $data['privacy']);

        $this->assertRequestStatus(200, 'PATCH', $uri, self::USER, ['privacy' => SavedSearchPrivacyEnum::Secret->value]);
        $this->assertRequestStatus(403, 'PATCH', $uri, self::USER, ['privacy' => SavedSearchPrivacyEnum::Public->value]);
        $this->assertSame(SavedSearchPrivacyEnum::Secret, $this->findSavedSearch($search->getId())->getPrivacy());
    }

    public function testOnlyAdminsManageTheTypeAces(): void
    {
        $ace = [
            'objectType' => Profile::OBJECT_TYPE,
            'userType' => 'group',
            'userId' => self::GROUP,
            'mask' => 0,
            'metadata' => [DataboxExtraPermissionInterface::PERM_CREATE_PROFILE],
        ];
        $query = ['objectType' => Profile::OBJECT_TYPE, 'objectId' => 'null'];

        // Denied: for a non-admin, the "null" object id is looked up as an object (and not found)
        $this->assertContains($this->request('GET', '/permissions/aces', self::USER, query: $query)->getStatusCode(), [403, 404]);
        $this->assertRequestStatus(403, 'PUT', '/permissions/ace', self::USER, $ace);
        $this->assertRequestStatus(403, 'DELETE', '/permissions/ace', self::USER, $ace);
        // Not even with the capability
        $this->grantType(Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_CREATE_PROFILE);
        $this->assertRequestStatus(403, 'PUT', '/permissions/ace', self::USER, $ace);

        $written = $this->assertRequestStatus(200, 'PUT', '/permissions/ace', self::ADMIN, $ace)->toArray();
        $this->assertNull($written['objectId']);
        $this->assertSame([DataboxExtraPermissionInterface::PERM_CREATE_PROFILE], $written['metadata']);

        $response = $this->request('GET', '/permissions/aces', self::ADMIN, query: $query);
        $this->assertSame(200, $response->getStatusCode());
        $aces = $response->toArray();
        $this->assertEqualsCanonicalizing([self::USER, self::GROUP], array_column($aces, 'userId'));
        $this->assertSame([null, null], array_column($aces, 'objectId'));

        $entry = self::getEntityManager()->getRepository(AccessControlEntry::class)->find($written['id']);
        $this->assertNull($entry->getObjectId());
        $this->assertSame(Profile::OBJECT_TYPE, $entry->getObjectType());

        $this->assertTrue($this->getCapabilities(self::OTHER, [self::GROUP])['createProfile']);

        // A write without metadata (the EasyAdmin editor only sends the mask) keeps the capability
        $this->assertRequestStatus(200, 'PUT', '/permissions/ace', self::ADMIN, ['mask' => PermissionInterface::VIEW] + array_diff_key($ace, ['metadata' => true]));
        $this->assertTrue($this->getCapabilities(self::OTHER, [self::GROUP])['createProfile']);

        $this->assertRequestStatus(200, 'DELETE', '/permissions/ace', self::ADMIN, $ace);
        $this->assertFalse($this->getCapabilities(self::OTHER, [self::GROUP])['createProfile']);
    }

    private function findSavedSearch(string $id): SavedSearch
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(SavedSearch::class, $id);
    }
}
