<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ACE management API (acl-bundle): /permissions/ace(s), and its aliases /ace(s).
 *
 * Reading or writing the ACEs of an object requires EDIT_PERMISSIONS on it
 * (admins can do anything). Users and groups to grant are looked up through
 * /permissions/users and /permissions/groups.
 */
final class PermissionAceTest extends AbstractDataboxTestCase
{
    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string MEMBER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string THIRD_USER = '77777777-fc1a-492b-9e76-c2e8e6979786';

    private function headers(?string $userId): array
    {
        return null === $userId ? [] : [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    /**
     * A workspace owned by OWNER, which MEMBER can see (VIEW).
     */
    private function createSharedWorkspace(): Workspace
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->addUserOnWorkspace(self::MEMBER, $workspace->getId());

        return $workspace;
    }

    private function putAce(string $as, string $objectType, string $objectId, string $userId, int $mask, array $extra = [], string $uri = '/permissions/ace'): array
    {
        $response = static::createClient()->request('PUT', $uri, [
            'headers' => $this->headers($as),
            'json' => [
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => 'user',
                'userId' => $userId,
                'mask' => $mask,
                ...$extra,
            ],
        ]);

        return $response->toArray(false);
    }

    private function deleteAce(string $as, string $objectType, string $objectId, string $userId, string $uri = '/permissions/ace'): void
    {
        static::createClient()->request('DELETE', $uri, [
            'headers' => $this->headers($as),
            'json' => [
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => 'user',
                'userId' => $userId,
            ],
        ]);
    }

    private function getAces(?string $as, string $objectType, string $objectId, array $query = [], string $uri = '/permissions/aces'): array
    {
        $response = static::createClient()->request('GET', $uri, [
            'headers' => $this->headers($as),
            'query' => [
                'objectType' => $objectType,
                'objectId' => $objectId,
                ...$query,
            ],
        ]);

        return $response->toArray(false);
    }

    private function findAce(string $userId, string $objectType, string $objectId): ?AccessControlEntry
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->getRepository(AccessControlEntry::class)->findOneBy([
            'userType' => AccessControlEntryInterface::TYPE_USER_VALUE,
            'userId' => $userId,
            'objectType' => $objectType,
            'objectId' => $objectId,
        ]);
    }

    private function getWorkspaceCapabilities(string $userId, string $workspaceId): array
    {
        $response = static::createClient()->request('GET', '/workspaces/'.$workspaceId, [
            'headers' => $this->headers($userId),
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray()['capabilities'];
    }

    public static function getAceRoutes(): iterable
    {
        yield 'under /permissions' => ['/permissions/ace', '/permissions/aces'];
        yield 'root aliases' => ['/ace', '/aces'];
    }

    #[DataProvider('getAceRoutes')]
    public function testWorkspaceOwnerManagesTheAcesOfItsWorkspace(string $aceUri, string $acesUri): void
    {
        $workspace = $this->createSharedWorkspace();
        $wsId = $workspace->getId();

        $ace = $this->putAce(self::OWNER, 'workspace', $wsId, self::THIRD_USER, PermissionInterface::VIEW | PermissionInterface::EDIT, uri: $aceUri);
        $this->assertResponseIsSuccessful();
        $this->assertMatchesUuid($ace['id']);
        $this->assertSame('user', $ace['userType']);
        $this->assertSame(self::THIRD_USER, $ace['userId']);
        $this->assertSame('workspace', $ace['objectType']);
        $this->assertSame($wsId, $ace['objectId']);
        $this->assertSame(5, $ace['mask']);
        $this->assertSame([], $ace['metadata']);
        // The user is resolved for display
        $this->assertArrayHasKey('username', $ace['user']);

        // Putting it again updates the same entry
        $updated = $this->putAce(self::OWNER, 'workspace', $wsId, self::THIRD_USER, PermissionInterface::VIEW, uri: $aceUri);
        $this->assertResponseIsSuccessful();
        $this->assertSame($ace['id'], $updated['id']);
        $this->assertSame(1, $updated['mask']);

        $aces = $this->getAces(self::OWNER, 'workspace', $wsId, uri: $acesUri);
        $this->assertResponseIsSuccessful();
        $masks = array_column($aces, 'mask', 'userId');
        // Owner (createWorkspace), member and third user
        $this->assertSame([
            self::OWNER => 1,
            self::MEMBER => 1,
            self::THIRD_USER => 1,
        ], array_intersect_key($masks, array_flip([self::OWNER, self::MEMBER, self::THIRD_USER])));
        $this->assertCount(3, $aces);

        $this->deleteAce(self::OWNER, 'workspace', $wsId, self::THIRD_USER, uri: $aceUri);
        $this->assertResponseIsSuccessful();
        $this->assertNull($this->findAce(self::THIRD_USER, 'workspace', $wsId));
        $this->assertNotContains(self::THIRD_USER, array_column($this->getAces(self::OWNER, 'workspace', $wsId, uri: $acesUri), 'userId'));
    }

    public function testAcesCanBeFilteredByUser(): void
    {
        $workspace = $this->createSharedWorkspace();

        $aces = $this->getAces(self::OWNER, 'workspace', $workspace->getId(), [
            'userType' => 'user',
            'userId' => self::MEMBER,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $aces);
        $this->assertSame(self::MEMBER, $aces[0]['userId']);
    }

    public function testAnInvalidUserTypeFilterIsRejected(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->getAces(self::OWNER, 'workspace', $workspace->getId(), ['userType' => 'robot']);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAnonymousCannotReadAces(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->getAces(null, 'workspace', $workspace->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAMemberCannotReadNorWriteTheAcesOfTheWorkspace(): void
    {
        $workspace = $this->createSharedWorkspace();
        $wsId = $workspace->getId();

        $this->getAces(self::MEMBER, 'workspace', $wsId);
        $this->assertResponseStatusCodeSame(403);

        // Cannot grant itself more rights…
        $this->putAce(self::MEMBER, 'workspace', $wsId, self::MEMBER, PermissionInterface::OWNER);
        $this->assertResponseStatusCodeSame(403);
        // …nor grant someone else
        $this->putAce(self::MEMBER, 'workspace', $wsId, self::THIRD_USER, PermissionInterface::VIEW);
        $this->assertResponseStatusCodeSame(403);
        // …nor revoke the others
        $this->deleteAce(self::MEMBER, 'workspace', $wsId, self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(PermissionInterface::VIEW, $this->findAce(self::MEMBER, 'workspace', $wsId)->getMask());
        $this->assertNull($this->findAce(self::THIRD_USER, 'workspace', $wsId));
        $this->assertNotNull($this->findAce(self::OWNER, 'workspace', $wsId));
    }

    public function testAcesOfAnObjectRequireAnObjectId(): void
    {
        $this->createSharedWorkspace();

        // Without an object, only admins may act (on the global ACEs)
        static::createClient()->request('PUT', '/permissions/ace', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'objectType' => 'workspace',
                'userType' => 'user',
                'userId' => self::OWNER,
                'mask' => PermissionInterface::OWNER,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminSetsAGlobalAceOnAnObjectType(): void
    {
        $ace = static::createClient()->request('PUT', '/permissions/ace', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => [
                'objectType' => 'workspace',
                'objectId' => '',
                'userType' => 'user',
                'userId' => self::MEMBER,
                'mask' => PermissionInterface::VIEW,
            ],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertNull($ace['objectId']);

        $em = self::getEntityManager();
        $entry = $em->getRepository(AccessControlEntry::class)->find($ace['id']);
        $this->assertNull($entry->getObjectId());
    }

    public function testAdminManagesTheAcesOfAnyObject(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->putAce(KeycloakClientTestMock::ADMIN_UID, 'workspace', $workspace->getId(), self::THIRD_USER, PermissionInterface::OWNER);
        $this->assertResponseIsSuccessful();
        $this->assertSame(PermissionInterface::OWNER, $this->findAce(self::THIRD_USER, 'workspace', $workspace->getId())->getMask());

        $this->getAces(KeycloakClientTestMock::ADMIN_UID, 'workspace', $workspace->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testCombinedMasksGrantEachPermissionButNotThePermissionManagement(): void
    {
        $workspace = $this->createSharedWorkspace();
        $wsId = $workspace->getId();

        $this->putAce(self::OWNER, 'workspace', $wsId, self::MEMBER, PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::DELETE);
        $this->assertResponseIsSuccessful();

        $capabilities = $this->getWorkspaceCapabilities(self::MEMBER, $wsId);
        $this->assertTrue($capabilities['edit']);
        $this->assertTrue($capabilities['delete']);
        $this->assertFalse($capabilities['createCollection']);
        $this->assertFalse($capabilities['editPermissions']);

        // EDIT is not OWNER: the ACEs stay out of reach
        $this->putAce(self::MEMBER, 'workspace', $wsId, self::MEMBER, PermissionInterface::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(13, $this->findAce(self::MEMBER, 'workspace', $wsId)->getMask());
    }

    public function testTheOwnerMaskDelegatesThePermissionManagement(): void
    {
        $workspace = $this->createSharedWorkspace();
        $wsId = $workspace->getId();

        $this->putAce(self::OWNER, 'workspace', $wsId, self::MEMBER, PermissionInterface::VIEW | PermissionInterface::OWNER);
        $this->assertResponseIsSuccessful();

        $capabilities = $this->getWorkspaceCapabilities(self::MEMBER, $wsId);
        $this->assertTrue($capabilities['editPermissions']);
        $this->assertTrue($capabilities['edit']);
        // DELETE is not implied by OWNER on a workspace
        $this->assertFalse($capabilities['delete']);

        $this->getAces(self::MEMBER, 'workspace', $wsId);
        $this->assertResponseIsSuccessful();
        $this->putAce(self::MEMBER, 'workspace', $wsId, self::THIRD_USER, PermissionInterface::VIEW);
        $this->assertResponseIsSuccessful();
        $this->assertSame(PermissionInterface::VIEW, $this->findAce(self::THIRD_USER, 'workspace', $wsId)->getMask());

        // Revoking OWNER takes the delegation back
        $this->putAce(self::OWNER, 'workspace', $wsId, self::MEMBER, PermissionInterface::VIEW);
        $this->putAce(self::MEMBER, 'workspace', $wsId, self::THIRD_USER, PermissionInterface::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(PermissionInterface::VIEW, $this->findAce(self::THIRD_USER, 'workspace', $wsId)->getMask());
    }

    public function testGrantingAndRevokingViewOnAnAssetShowsAndHidesIt(): void
    {
        $workspace = $this->createSharedWorkspace();
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::OWNER,
            'name' => 'Secret asset',
        ]);
        $assetId = $asset->getId();

        static::createClient()->request('GET', '/assets/'.$assetId, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->putAce(self::OWNER, Asset::OBJECT_TYPE, $assetId, self::MEMBER, PermissionInterface::VIEW);
        $this->assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', '/assets/'.$assetId, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($assetId, $response->toArray()['id']);
        // VIEW only: no edition
        $capabilities = $response->toArray()['capabilities'];
        $this->assertFalse($capabilities['edit']);
        $this->assertFalse($capabilities['delete']);
        $this->assertFalse($capabilities['editPermissions']);

        $this->deleteAce(self::OWNER, Asset::OBJECT_TYPE, $assetId, self::MEMBER);
        $this->assertResponseIsSuccessful();

        static::createClient()->request('GET', '/assets/'.$assetId, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnAssetAceIsUselessWithoutAccessToTheWorkspace(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::OWNER,
        ]);
        $this->grantUserOnObject(self::MEMBER, $asset, PermissionInterface::VIEW);

        static::createClient()->request('GET', '/assets/'.$asset->getId(), [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnAssetOwnerNeedsTheExtraPermissionToManageItsAces(): void
    {
        $workspace = $this->createSharedWorkspace();
        $wsId = $workspace->getId();
        // The member owns the asset, in a workspace it does not own
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::MEMBER,
        ]);
        $assetId = $asset->getId();

        $this->getAces(self::MEMBER, Asset::OBJECT_TYPE, $assetId);
        $this->assertResponseStatusCodeSame(403);
        $this->putAce(self::MEMBER, Asset::OBJECT_TYPE, $assetId, self::THIRD_USER, PermissionInterface::VIEW);
        $this->assertResponseStatusCodeSame(403);

        // The workspace owner grants the "edit permissions" extra permission
        // (an ACE metadata) on the workspace
        $this->putAce(self::OWNER, 'workspace', $wsId, self::MEMBER, PermissionInterface::VIEW, [
            'metadata' => [DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS], $this->findAce(self::MEMBER, 'workspace', $wsId)->getMetadata());

        $this->putAce(self::MEMBER, Asset::OBJECT_TYPE, $assetId, self::THIRD_USER, PermissionInterface::VIEW);
        $this->assertResponseIsSuccessful();
        $this->assertSame(PermissionInterface::VIEW, $this->findAce(self::THIRD_USER, Asset::OBJECT_TYPE, $assetId)->getMask());

        // The extra permission only applies to the assets the member owns
        $ownerAsset = $this->createAsset([
            'workspace' => $this->findWorkspace($wsId),
            'ownerId' => self::OWNER,
        ]);
        $this->putAce(self::MEMBER, Asset::OBJECT_TYPE, $ownerAsset->getId(), self::THIRD_USER, PermissionInterface::VIEW);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheWorkspaceOwnerManagesTheAcesOfEveryAssetOfIt(): void
    {
        $workspace = $this->createSharedWorkspace();
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::MEMBER,
        ]);

        $this->putAce(self::OWNER, Asset::OBJECT_TYPE, $asset->getId(), self::THIRD_USER, PermissionInterface::EDIT);

        $this->assertResponseIsSuccessful();
    }

    public static function getMalformedAceRequests(): iterable
    {
        $base = [
            'objectType' => 'workspace',
            'userType' => 'user',
            'userId' => self::THIRD_USER,
            'mask' => 1,
        ];

        yield 'PUT with an unknown object type' => ['PUT', ['objectType' => 'unknown'] + $base];
        yield 'PUT with an unknown user type' => ['PUT', ['userType' => 'robot'] + $base];
        yield 'PUT without user type' => ['PUT', array_diff_key($base, ['userType' => true])];
        yield 'PUT without user' => ['PUT', array_diff_key($base, ['userId' => true])];
        yield 'DELETE without user' => ['DELETE', array_diff_key($base, ['userId' => true])];
        yield 'GET with an unknown object type' => ['GET', ['objectType' => 'unknown'] + $base];
    }

    #[DataProvider('getMalformedAceRequests')]
    public function testMalformedAceRequestsAreRejectedCleanly(string $method, array $payload): void
    {
        $this->markTestIncomplete('BUG: PermissionController does not validate its input: an unknown objectType (ObjectMapping::getClassName throws \InvalidArgumentException), an unknown/missing userType (AccessControlEntry::getUserTypeFromString) or a missing userId (typed string arguments) end in a 500 instead of a 400 (vendor/alchemy/acl-bundle/src/Controller/PermissionController.php:41,68,128).');

        $workspace = $this->createSharedWorkspace();
        $payload['objectId'] = $workspace->getId();

        static::createClient()->request($method, 'GET' === $method ? '/permissions/aces' : '/permissions/ace', [
            'headers' => $this->headers(self::OWNER),
            ...('GET' === $method ? ['query' => $payload] : ['json' => $payload]),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAnInvalidJsonBodyIsRejected(): void
    {
        $this->markTestIncomplete('BUG: JsonConverterSubscriber also runs on the error sub-request, which carries the same invalid body: the BadRequestHttpException is thrown again while rendering the error and escapes the kernel (500 in production) instead of a 400 (lib/php/core-bundle/Listener/JsonConverterSubscriber.php:22, missing isMainRequest() check).');

        $workspace = $this->createSharedWorkspace();

        static::createClient()->request('PUT', '/permissions/ace', [
            'headers' => [
                ...$this->headers(self::OWNER),
                'Content-Type' => 'application/json',
            ],
            'body' => '{"objectType": "workspace", "objectId": "'.$workspace->getId().'",',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUsersAndGroupsDirectoryRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/permissions/users');
        $this->assertResponseStatusCodeSame(401);
        $client->request('GET', '/permissions/groups');
        $this->assertResponseStatusCodeSame(401);

        $response = $client->request('GET', '/permissions/users', [
            'headers' => $this->headers(self::MEMBER),
            'query' => ['query' => 'jo', 'limit' => 5],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response->toArray());

        $response = $client->request('GET', '/permissions/groups', [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response->toArray());
    }

    private function findWorkspace(string $id): Workspace
    {
        return self::getEntityManager()->find(Workspace::class, $id);
    }
}
