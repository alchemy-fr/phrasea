<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /assets/{id}: who can read an asset (owner, ACL, privacy, collections,
 * anonymous), what is hidden (trash, quarantine) and the computed fields.
 */
final class AssetReadTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testOwnerReadsHisSecretAsset(): void
    {
        $workspace = $this->createOwnedWorkspace('custom_owner');
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'name' => 'My asset']);

        $response = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
        $data = $response->toArray();
        $this->assertSame('/assets/'.$asset->getId(), $data['@id']);
        $this->assertSame('asset', $data['@type']);
        $this->assertSame('My asset', $data['name']);
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $data['privacy']);
        $this->assertFalse($data['deleted']);
        $this->assertSame(AssetStatusEnum::Accepted->value, $data['status']);
        $this->assertSame($asset->getId(), $data['resolvedTrackingId']);
        $this->assertSame('/workspaces/'.$workspace->getId(), $data['workspace']['@id']);
        $this->assertSame(self::OWNER, $data['owner']['id']);

        // The owner of a secret asset may edit/share/delete it, but not change
        // its permissions without being granted that on the workspace.
        $this->assertSame([
            'edit' => true,
            'editAttributes' => true,
            'share' => true,
            'delete' => true,
            'editPermissions' => false,
        ], $data['capabilities']);
    }

    public function testWorkspaceOwnerHasEveryCapability(): void
    {
        $this->createOwnedWorkspace(self::OWNER);
        $asset = $this->createAsset(['ownerId' => self::OTHER]);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'edit' => true,
            'editAttributes' => true,
            'share' => true,
            'delete' => true,
            'editPermissions' => true,
        ], $data['capabilities']);
    }

    public function testUnknownAssetIs404(): void
    {
        $this->createOwnedWorkspace();

        $this->request('GET', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function memberPrivacyProvider(): iterable
    {
        yield 'secret' => [WorkspaceItemPrivacyInterface::SECRET, false];
        yield 'private in workspace' => [WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE, false];
        yield 'public in workspace' => [WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, true];
        yield 'private' => [WorkspaceItemPrivacyInterface::PRIVATE, true];
        yield 'public for users' => [WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS, true];
        yield 'public' => [WorkspaceItemPrivacyInterface::PUBLIC, true];
    }

    /**
     * A member of the workspace (VIEW on the workspace) reads assets from
     * "public in workspace" upward; below that, only granted users can.
     */
    #[DataProvider('memberPrivacyProvider')]
    public function testWorkspaceMemberAccessDependsOnPrivacy(int $privacy, bool $granted): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createPrivacyAsset($privacy);

        $response = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);

        $this->assertResponseStatusCodeSame($granted ? 200 : 403);
        if ($granted) {
            // A reader has no other capability
            $this->assertSame([
                'edit' => false,
                'editAttributes' => false,
                'share' => false,
                'delete' => false,
                'editPermissions' => false,
            ], $response->toArray()['capabilities']);
        }
    }

    #[DataProvider('memberPrivacyProvider')]
    public function testUserOutsideOfAPrivateWorkspaceNeverReadsItsAssets(int $privacy): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createPrivacyAsset($privacy);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnonymousReadsAPublicAssetOfAPublicWorkspace(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::PUBLIC);

        $data = $this->request('GET', '/assets/'.$asset->getId(), null)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame($asset->getId(), $data['id']);
        $this->assertFalse($data['capabilities']['edit']);
        $this->assertFalse($data['capabilities']['delete']);
        $this->assertArrayNotHasKey('topicSubscriptions', $data);
    }

    public function testAnonymousCannotReadASecretAssetOfAPublicWorkspace(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::SECRET);

        $this->request('GET', '/assets/'.$asset->getId(), null);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotReadAPublicAssetOfAPrivateWorkspace(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::PUBLIC);

        $this->request('GET', '/assets/'.$asset->getId(), null);
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPublicPrivacyProvider(): iterable
    {
        yield 'public_in_workspace' => [WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE];
        yield 'private' => [WorkspaceItemPrivacyInterface::PRIVATE];
        yield 'public_for_users' => [WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS];
    }

    /**
     * As in the search, anonymous users only read PUBLIC assets.
     */
    #[DataProvider('nonPublicPrivacyProvider')]
    public function testAnonymousCannotReadANonPublicAssetOfAPublicWorkspace(int $privacy): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createPrivacyAsset($privacy);

        $this->request('GET', '/assets/'.$asset->getId(), null);
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * A non-public collection does not open its assets to anonymous users
     * (whereas a public one does), but still opens them to authenticated users.
     */
    #[DataProvider('nonPublicPrivacyProvider')]
    public function testAnonymousCannotReadTheAssetsOfANonPublicCollection(int $privacy): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $collection->setPrivacy($privacy);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);
        self::getEntityManager()->flush();

        $this->request('GET', '/assets/'.$asset->getId(), null);
        $this->assertResponseStatusCodeSame(401);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();

        $collection = self::getEntityManager()->find(Collection::class, $collection->getId());
        $collection->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC);
        self::getEntityManager()->flush();

        $this->request('GET', '/assets/'.$asset->getId(), null);
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserReadsAnAssetOfAPublicWorkspace(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{int, array<string, bool>}>
     */
    public static function aclProvider(): iterable
    {
        $none = ['edit' => false, 'editAttributes' => false, 'share' => false, 'delete' => false, 'editPermissions' => false];

        yield 'VIEW' => [PermissionInterface::VIEW, $none];
        // EDIT only allows editing attributes, OPERATOR only the asset itself (two distinct bits)
        yield 'VIEW + EDIT' => [PermissionInterface::VIEW | PermissionInterface::EDIT, array_merge($none, ['editAttributes' => true])];
        yield 'VIEW + OPERATOR' => [PermissionInterface::VIEW | PermissionInterface::OPERATOR, array_merge($none, ['edit' => true])];
        yield 'VIEW + EDIT + OPERATOR' => [PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::OPERATOR, array_merge($none, ['edit' => true, 'editAttributes' => true])];
        yield 'VIEW + DELETE' => [PermissionInterface::VIEW | PermissionInterface::DELETE, array_merge($none, ['delete' => true])];
        yield 'VIEW + SHARE' => [PermissionInterface::VIEW | PermissionInterface::SHARE, array_merge($none, ['share' => true])];
        // OWNER does not imply SHARE nor changing permissions
        yield 'OWNER' => [PermissionInterface::OWNER, array_merge($none, ['edit' => true, 'editAttributes' => true, 'delete' => true])];
    }

    /**
     * ACEs on the asset itself open a secret asset and drive the capabilities.
     */
    #[DataProvider('aclProvider')]
    public function testAclOnTheAssetDrivesReadAndCapabilities(int $mask, array $expectedCapabilities): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::SECRET);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::OTHER, $asset, $mask);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($expectedCapabilities, $data['capabilities']);
    }

    public function testChildViewOnAReferenceCollectionOpensItsSecretAssets(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::CHILD_VIEW);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['capabilities']['edit']);
        // CHILD_VIEW opens the assets, not the (secret) collection itself
        $this->assertArrayNotHasKey('referenceCollection', $data);
        $this->assertSame([], $data['collections']);

        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);
        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER)->toArray();
        $this->assertSame('/collections/'.$collection->getId(), $data['referenceCollection']['@id']);
    }

    public function testAnyCollectionOfTheAssetCanOpenIt(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $reference = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Reference']);
        $linked = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Linked']);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());

        $this->grantUserOnObject(self::OTHER, $linked, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER)->toArray();
        $this->assertResponseIsSuccessful();

        // The secret reference collection is not disclosed
        $this->assertArrayNotHasKey('referenceCollection', $data);
        $this->assertSame(['Linked'], array_column($data['collections'], 'name'));
    }

    public function testCollectionsListedOnTheAssetAreFilteredByReadability(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $secret = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Secret']);
        $open = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Open']);
        $open->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $secret->getId()]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $this->addAssetToCollection($open->getId(), $asset->getId());

        $ownerData = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertEqualsCanonicalizing(['Secret', 'Open'], array_column($ownerData['collections'], 'name'));

        $otherData = $this->request('GET', '/assets/'.$asset->getId(), self::OTHER)->toArray();
        $this->assertSame(['Open'], array_column($otherData['collections'], 'name'));
    }

    public function testTrashedAssetIsOnlyReadableByWhoCanDeleteIt(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, ['no_flush' => true]);
        $asset->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['deleted']);

        // DELETE granted: the asset can be seen in the trash (to be restored)
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::DELETE);
        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();
    }

    public function testAssetOfATrashedCollectionIsReportedDeleted(): void
    {
        $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);
        $collection->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['deleted']);
    }

    /**
     * @return iterable<string, array{AssetStatusEnum}>
     */
    public static function hiddenStatusProvider(): iterable
    {
        yield 'pending' => [AssetStatusEnum::Pending];
        yield 'quarantined' => [AssetStatusEnum::Quarantined];
    }

    #[DataProvider('hiddenStatusProvider')]
    public function testNonAcceptedAssetIsHiddenFromReaders(AssetStatusEnum $status): void
    {
        $workspace = $this->createOwnedWorkspace('custom_owner');
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createPrivacyAsset(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, ['no_flush' => true]);
        $asset->setStatus($status);
        self::getEntityManager()->flush();

        // A plain reader (even with a VIEW ACE) does not see it
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW);
        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        // Its owner does
        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($status->value, $data['status']);
    }

    public function testBypassQuarantineCapabilityIsOnlyExposedOnQuarantinedAssets(): void
    {
        $workspace = $this->createOwnedWorkspace(self::OTHER);
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $quarantined = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $quarantined->setStatus(AssetStatusEnum::Quarantined);
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertArrayNotHasKey('bypassQuarantine', $data['capabilities']);

        // The asset owner sees his quarantined asset but bypassing it is a workspace permission
        $data = $this->request('GET', '/assets/'.$quarantined->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['capabilities']['bypassQuarantine']);

        $data = $this->request('GET', '/assets/'.$quarantined->getId(), self::OTHER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['capabilities']['bypassQuarantine']);
    }

    public function testAdminReadsAnySecretAsset(): void
    {
        $this->createOwnedWorkspace('custom_owner');
        $asset = $this->createAsset(['ownerId' => 'custom_owner']);

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::ADMIN)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['capabilities']['editPermissions']);
    }

    public function testReadExposesTrackingIdentifiersAndExtraMetadata(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setExternalId('ext-42');
        $asset->setExtraMetadata(['source' => 'test']);
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertSame('ext-42', $data['externalId']);
        // Falls back to the external ID when no tracking ID is set
        $this->assertSame('ext-42', $data['resolvedTrackingId']);
        $this->assertSame(['source' => 'test'], $data['extraMetadata']);

        $asset = $this->reloadAsset($asset->getId());
        $asset->setTrackingId('track-1');
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertSame('track-1', $data['trackingId']);
        $this->assertSame('track-1', $data['resolvedTrackingId']);
    }

    private function createPrivacyAsset(int $privacy, array $options = []): Asset
    {
        $asset = $this->createAsset(array_merge(['ownerId' => self::OWNER, 'no_flush' => true], $options));
        $asset->setPrivacy($privacy);
        if (!($options['no_flush'] ?? false)) {
            self::getEntityManager()->flush();
        }

        return $asset;
    }
}
