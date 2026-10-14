<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * DELETE /assets/{id} (hard delete), POST /assets/delete-multiple (trash,
 * hard delete or unlink from collections), POST /assets/restore-multiple and
 * DELETE /assets-by-keys.
 */
final class AssetDeleteTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testDeleteIsAHardDelete(): void
    {
        $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);
        $assetId = $asset->getId();

        $this->request('DELETE', '/assets/'.$assetId, self::OWNER);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->reloadAsset($assetId));
        $this->assertCount(0, self::getEntityManager()->getRepository(CollectionAsset::class)->findBy(['asset' => $assetId]));
        // The collection itself is kept
        $this->assertNotNull(self::getEntityManager()->find(Collection::class, $collection->getId()));

        $this->request('GET', '/assets/'.$assetId, self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function deleteAclProvider(): iterable
    {
        yield 'VIEW' => [PermissionInterface::VIEW, 403];
        yield 'OPERATOR' => [PermissionInterface::VIEW | PermissionInterface::OPERATOR, 403];
        yield 'DELETE' => [PermissionInterface::VIEW | PermissionInterface::DELETE, 204];
        yield 'OWNER' => [PermissionInterface::OWNER, 204];
    }

    #[DataProvider('deleteAclProvider')]
    public function testDeleteRequiresTheDeletePermission(int $mask, int $expectedStatus): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $asset, $mask);

        $this->request('DELETE', '/assets/'.$asset->getId(), self::OTHER);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $this->assertSame(204 !== $expectedStatus, null !== $this->reloadAsset($asset->getId()));
    }

    public function testChildDeleteOnTheWorkspaceAllowsDeletingItsAssets(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_DELETE);

        $this->request('DELETE', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteAsAnonymousOrUnknownAsset(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'public' => true]);

        $this->request('DELETE', '/assets/'.$asset->getId(), null);
        $this->assertResponseStatusCodeSame(401);

        $this->request('DELETE', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteMultipleMovesToTheTrashThenRestore(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $first = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $first->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $second = $this->createAsset(['ownerId' => self::OWNER]);
        $ids = [$first->getId(), $second->getId()];

        $this->request('POST', '/assets/delete-multiple', self::OWNER, ['ids' => $ids]);
        $this->assertResponseStatusCodeSame(204);

        foreach ($ids as $id) {
            $asset = $this->reloadAsset($id);
            $this->assertNotNull($asset, 'A trashed asset is kept');
            $this->assertNotNull($asset->getDeletedAt());
        }

        // Out of sight for its readers, still visible (as deleted) to who can restore it
        $this->request('GET', '/assets/'.$first->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $data = $this->request('GET', '/assets/'.$first->getId(), self::OWNER)->toArray();
        $this->assertTrue($data['deleted']);
        $this->assertTrue($data['capabilities']['delete']);

        $this->request('POST', '/assets/restore-multiple', self::OWNER, ['ids' => $ids]);
        $this->assertResponseStatusCodeSame(204);

        foreach ($ids as $id) {
            $this->assertNull($this->reloadAsset($id)->getDeletedAt());
        }
        $data = $this->request('GET', '/assets/'.$first->getId(), self::OTHER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['deleted']);
    }

    public function testDeleteMultipleWithHardDelete(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('POST', '/assets/delete-multiple', self::OWNER, [
            'ids' => [$asset->getId()],
            'hardDelete' => true,
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->reloadAsset($asset->getId()));
    }

    public function testDeleteMultipleIsDeniedAsAWhole(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);
        $mine = $this->createAsset(['ownerId' => self::OTHER]);
        $notMine = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('POST', '/assets/delete-multiple', self::OTHER, ['ids' => [$mine->getId(), $notMine->getId()]]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->reloadAsset($mine->getId())->getDeletedAt());
        $this->assertNull($this->reloadAsset($notMine->getId())->getDeletedAt());
    }

    public function testDeleteMultipleRequiresIds(): void
    {
        $this->createOwnedWorkspace();

        $this->request('POST', '/assets/delete-multiple', self::OWNER, ['ids' => []]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('POST', '/assets/delete-multiple', self::OWNER, []);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDeleteMultipleAsAnonymous(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'public' => true]);

        $this->request('POST', '/assets/delete-multiple', null, ['ids' => [$asset->getId()]]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertNull($this->reloadAsset($asset->getId())->getDeletedAt());
    }

    /**
     * With "collections", the assets are only removed from these collections
     * (never from their reference collection) and not deleted.
     */
    public function testDeleteMultipleFromCollectionsOnlyUnlinks(): void
    {
        $this->createOwnedWorkspace();
        $reference = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Reference']);
        $linked = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Linked']);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());

        $this->request('POST', '/assets/delete-multiple', self::OWNER, [
            'ids' => [$asset->getId()],
            'collections' => [$reference->getId(), $linked->getId()],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $asset = $this->reloadAsset($asset->getId());
        $this->assertNull($asset->getDeletedAt());
        $this->assertSame($reference->getId(), $asset->getReferenceCollectionId());
        $this->assertSame(
            [$reference->getId()],
            array_map(fn (CollectionAsset $ca): string => $ca->getCollection()->getId(), $asset->getCollections()->getValues()),
        );
    }

    public function testUnlinkRequiresEditOnTheCollections(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $linked = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OTHER]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());

        // Owning the asset is not enough
        $this->request('POST', '/assets/delete-multiple', self::OTHER, [
            'ids' => [$asset->getId()],
            'collections' => [$linked->getId()],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertCount(1, $this->reloadAsset($asset->getId())->getCollections());

        $this->grantUserOnObject(self::OTHER, $linked, PermissionInterface::VIEW | PermissionInterface::EDIT);
        $this->request('POST', '/assets/delete-multiple', self::OTHER, [
            'ids' => [$asset->getId()],
            'collections' => [$linked->getId()],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertCount(0, $this->reloadAsset($asset->getId())->getCollections());
    }

    public function testTrashingAStoryTrashesItsCollectionAndRestoringRestoresIt(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $story = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);
        $storyCollectionId = $story['storyCollection']['id'];

        $this->request('POST', '/assets/delete-multiple', self::OWNER, ['ids' => [$story['id']]]);
        $this->assertResponseStatusCodeSame(204);
        self::getEntityManager()->clear();
        $this->assertNotNull(self::getEntityManager()->find(Collection::class, $storyCollectionId)->getDeletedAt());

        $this->request('POST', '/assets/restore-multiple', self::OWNER, ['ids' => [$story['id']]]);
        $this->assertResponseStatusCodeSame(204);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Collection::class, $storyCollectionId)->getDeletedAt());
        $this->assertNull($this->reloadAsset($story['id'])->getDeletedAt());
    }

    public function testRestoreRequiresTheDeletePermission(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $asset->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->request('POST', '/assets/restore-multiple', self::OTHER, ['ids' => [$asset->getId()]]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->reloadAsset($asset->getId())->getDeletedAt());

        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW | PermissionInterface::DELETE);
        $this->request('POST', '/assets/restore-multiple', self::OTHER, ['ids' => [$asset->getId()]]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->reloadAsset($asset->getId())->getDeletedAt());
    }

    public function testRestoreRequiresIds(): void
    {
        $this->createOwnedWorkspace();

        $this->request('POST', '/assets/restore-multiple', self::OWNER, ['ids' => []]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDeleteByKeysMovesToTheTrash(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');
        $a = $this->createKeyedAsset('key-a');
        $b = $this->createKeyedAsset('key-b');
        $kept = $this->createKeyedAsset('key-c');
        $sameKeyElsewhere = $this->createKeyedAsset('key-a', ['workspace' => $otherWorkspace]);

        $this->request('DELETE', '/assets-by-keys', self::OWNER, [
            'workspaceId' => $workspace->getId(),
            'keys' => ['key-a', 'key-b', 'unknown-key'],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNotNull($this->reloadAsset($a->getId())->getDeletedAt());
        $this->assertNotNull($this->reloadAsset($b->getId())->getDeletedAt());
        $this->assertNull($this->reloadAsset($kept->getId())->getDeletedAt());
        $this->assertNull($this->reloadAsset($sameKeyElsewhere->getId())->getDeletedAt());
    }

    public function testDeleteByKeysRequiresKeysAndWorkspace(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $response = $this->request('DELETE', '/assets-by-keys', self::OWNER, ['workspaceId' => $workspace->getId()]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('keys', $this->errorMessage($response));

        $response = $this->request('DELETE', '/assets-by-keys', self::OWNER, ['keys' => ['a']]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('workspace', $this->errorMessage($response));
    }

    public function testDeleteByKeysOfAnotherOwnerIsDenied(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);
        $asset = $this->createKeyedAsset('owned-by-user');

        $this->request('DELETE', '/assets-by-keys', self::OTHER, [
            'workspaceId' => $workspace->getId(),
            'keys' => ['owned-by-user'],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->reloadAsset($asset->getId())->getDeletedAt());

        $this->request('DELETE', '/assets-by-keys', null, [
            'workspaceId' => $workspace->getId(),
            'keys' => ['owned-by-user'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testPrepareDeleteDescribesTheImpact(): void
    {
        $workspace = $this->createOwnedWorkspace('custom_owner');
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $reference = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Reference']);
        $linked = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Linked']);
        $notEditable = $this->createCollection(['ownerId' => self::OTHER, 'name' => 'Not editable']);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());
        $this->addAssetToCollection($notEditable->getId(), $asset->getId());
        $alone = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('POST', '/shares', self::ADMIN, ['assets' => ['/assets/'.$asset->getId()]]);
        $this->assertResponseStatusCodeSame(201);

        $data = $this->request('POST', '/assets/prepare-delete', self::OWNER, [
            'ids' => [$asset->getId(), $alone->getId()],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['canDelete']);
        $this->assertSame(1, $data['shareCount']);
        // Neither the reference collection, nor the collections the user cannot edit
        $this->assertSame(['Linked'], array_column($data['collections'], 'name'));
        $this->assertSame($workspace->getId(), $this->reloadAsset($asset->getId())->getWorkspaceId());
    }

    public function testPrepareDeleteForAReader(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $readable = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $readable->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $secret = $this->createAsset(['ownerId' => self::OWNER]);

        $data = $this->request('POST', '/assets/prepare-delete', self::OTHER, ['ids' => [$readable->getId()]])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['canDelete']);
        $this->assertSame(0, $data['shareCount']);
        $this->assertSame([], $data['collections']);

        $this->request('POST', '/assets/prepare-delete', self::OTHER, ['ids' => [$readable->getId(), $secret->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets/prepare-delete', null, ['ids' => [$readable->getId()]]);
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * delete-multiple is denied as soon as one asset cannot be deleted, so
     * prepare-delete should not announce the selection as deletable.
     */
    public function testPrepareDeleteCannotDeleteAMixedSelection(): void
    {
        $this->markTestIncomplete('BUG: PrepareDeleteAssetProcessor::process() sets canDelete as soon as ONE asset is deletable (`if (!$canDelete && isGranted(DELETE))`), whereas AssetsDeleteProcessor denies the whole batch when one asset is not (src/Api/Processor/PrepareDeleteAssetProcessor.php:40).');

        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $mine = $this->createAsset(['ownerId' => self::OTHER]);
        $notMine = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $notMine->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        self::getEntityManager()->flush();

        $data = $this->request('POST', '/assets/prepare-delete', self::OTHER, [
            'ids' => [$mine->getId(), $notMine->getId()],
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['canDelete']);
    }

    private function createKeyedAsset(string $key, array $options = []): Asset
    {
        $asset = $this->createAsset(array_merge(['ownerId' => self::OWNER], $options, ['no_flush' => true]));
        $asset->setKey($key);
        self::getEntityManager()->flush();

        return $asset;
    }
}
