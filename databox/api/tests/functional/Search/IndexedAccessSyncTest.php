<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Consumer\Handler\Collection\CollectionsMoveToTrash;
use App\Consumer\Handler\Collection\CollectionsRestore;
use App\Doctrine\Delete\CollectionDelete;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;
use Elastica\Exception\NotFoundException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The access rights denormalized in the asset and collection documents follow
 * the changes of owner, the trash and the purge.
 */
final class IndexedAccessSyncTest extends AbstractSearchTestCase
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('collection');
        self::waitForESIndex('asset');
    }

    public function testCollectionOwnerChangeReindexesTheSubCollections(): void
    {
        $parent = $this->createCollection(['name' => 'Parent', 'ownerId' => 'old_owner']);
        $child = $this->createCollection(['name' => 'Child', 'parent' => $parent, 'ownerId' => 'child_owner']);
        self::releaseIndex();

        $this->assertContains('old_owner', $this->getDocument('collection', $child->getId())['users']);

        // The children of an entity created in this test are not in its (inverse) collection
        $em = self::getEntityManager();
        $em->clear();
        $parent = $em->find(Collection::class, $parent->getId());
        $parent->setOwnerId('new_owner');
        $em->flush();
        self::releaseIndex();

        $users = $this->getDocument('collection', $child->getId())['users'];
        $this->assertNotContains('old_owner', $users);
        $this->assertContains('new_owner', $users);
    }

    public function testWorkspaceOwnerChangeReindexesItsCollectionsAndAssets(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => 'old_owner', 'no_acl' => true]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'ownerId' => 'coll_owner']);
        $asset = $this->createAsset(['workspace' => $workspace, 'collectionId' => $collection->getId(), 'ownerId' => 'asset_owner']);
        self::releaseIndex();

        $this->assertContains('old_owner', $this->getDocument('collection', $collection->getId())['users']);
        $this->assertContains('old_owner', $this->getDocument('asset', $asset->getId())['users']);

        $em = self::getEntityManager();
        $workspace = $em->find(Workspace::class, $workspace->getId());
        $workspace->setOwnerId('new_owner');
        $em->flush();
        self::releaseIndex();

        foreach ([['collection', $collection->getId()], ['asset', $asset->getId()]] as [$index, $id]) {
            $users = $this->getDocument($index, $id)['users'];
            $this->assertNotContains('old_owner', $users, $index);
            $this->assertContains('new_owner', $users, $index);
        }
    }

    public function testTrashAndRestoreReachTheDescendants(): void
    {
        $parent = $this->createCollection(['name' => 'Parent']);
        $child = $this->createCollection(['name' => 'Child', 'parent' => $parent]);
        $grandChild = $this->createCollection(['name' => 'Grand child', 'parent' => $child]);
        $trashedChild = $this->createCollection(['name' => 'Trashed child', 'parent' => $parent]);
        self::releaseIndex();

        // The children of an entity created in this test are not in its (inverse) collection
        $em = self::getEntityManager();
        $em->clear();
        $em->find(Collection::class, $trashedChild->getId())->setDeletedAt(new \DateTimeImmutable());
        $em->flush();
        self::releaseIndex();
        $this->assertTrue($this->getDocument('collection', $trashedChild->getId())['deleted']);
        $this->assertFalse($this->getDocument('collection', $child->getId())['deleted']);

        $this->dispatch(new CollectionsMoveToTrash([$parent->getId()]));
        self::releaseIndex();

        foreach ([$parent, $child, $grandChild, $trashedChild] as $collection) {
            $this->assertTrue($this->getDocument('collection', $collection->getId())['deleted'], $collection->getName());
        }

        $this->dispatch(new CollectionsRestore([$parent->getId()]));
        self::releaseIndex();

        foreach ([$parent, $child, $grandChild] as $collection) {
            $this->assertFalse($this->getDocument('collection', $collection->getId())['deleted'], $collection->getName());
        }
        $this->assertTrue($this->getDocument('collection', $trashedChild->getId())['deleted'], 'it has its own trash date');
    }

    public function testTrashedLinkedCollectionNoLongerGrantsItsAssets(): void
    {
        $reference = $this->createCollection(['name' => 'Reference', 'ownerId' => 'another_owner']);
        $shared = $this->createCollection(['name' => 'Shared', 'ownerId' => 'another_owner']);
        $asset = $this->createAsset(['name' => 'Foo', 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($shared->getId(), $asset->getId());
        $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $reference->getWorkspace(), PermissionInterface::VIEW);
        $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $shared, PermissionInterface::VIEW);
        // The link was persisted on its own: the managed asset does not hold it
        self::getEntityManager()->clear();
        self::releaseIndex();

        $this->assertSame([$asset->getId()], $this->searchAssetIds());

        $this->dispatch(new CollectionsMoveToTrash([$shared->getId()]));
        self::releaseIndex();

        $this->assertNotContains(KeycloakClientTestMock::USER_UID, $this->getDocument('asset', $asset->getId())['users']);
        $this->assertSame([], $this->searchAssetIds());
    }

    public function testPurgeRemovesTheBranchAndReindexesTheLinkedAssets(): void
    {
        $reference = $this->createCollection(['name' => 'Reference', 'ownerId' => 'another_owner']);
        $parent = $this->createCollection(['name' => 'Parent', 'ownerId' => 'another_owner']);
        $child = $this->createCollection(['name' => 'Child', 'parent' => $parent, 'ownerId' => 'another_owner']);
        $asset = $this->createAsset(['name' => 'Linked', 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($child->getId(), $asset->getId());
        $purgedAsset = $this->createAsset(['name' => 'Purged', 'collectionId' => $child->getId()]);
        $childPath = $child->getAbsolutePath();
        // The link was persisted on its own: the managed asset does not hold it
        self::getEntityManager()->clear();
        self::releaseIndex();

        $this->assertContains($childPath, $this->getDocument('asset', $asset->getId())['collectionPaths']);

        $this->dispatch(new CollectionsMoveToTrash([$parent->getId()]));
        self::releaseIndex();
        self::getService(CollectionDelete::class)->deleteCollection($parent->getId());
        self::releaseIndex();

        $this->assertNull(self::getEntityManager()->find(Collection::class, $parent->getId()));
        $this->assertNull(self::getEntityManager()->find(Asset::class, $purgedAsset->getId()));
        foreach ([$parent, $child] as $collection) {
            $this->assertNull($this->findDocument('collection', $collection->getId()), $collection->getName());
        }
        $this->assertNull($this->findDocument('asset', $purgedAsset->getId()));
        $this->assertNotContains($childPath, $this->getDocument('asset', $asset->getId())['collectionPaths']);
    }

    private function dispatch(object $message): void
    {
        self::getService(MessageBusInterface::class)->dispatch($message);
    }

    /**
     * @return string[]
     */
    private function searchAssetIds(): array
    {
        $response = self::createClient()->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $data = $this->getDataFromResponse($response, 200);
        $ids = array_map(fn (array $asset): string => $asset['id'], $data['member']);
        $this->assertSame(count($ids), $data['totalItems']);

        return $ids;
    }

    private function getDocument(string $index, string $id): array
    {
        $document = $this->findDocument($index, $id);
        $this->assertNotNull($document, sprintf('%s "%s" is indexed', $index, $id));

        return $document;
    }

    private function findDocument(string $index, string $id): ?array
    {
        try {
            return self::$documentIndices[$index]->getDocument($id)->getData();
        } catch (NotFoundException) {
            return null;
        }
    }
}
