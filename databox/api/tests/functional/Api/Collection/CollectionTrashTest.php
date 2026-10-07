<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /collections/delete-multiple and /collections/restore-multiple.
 */
final class CollectionTrashTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    public function testMoveToTrashAndRestore(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER, self::OTHER]]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A', 'ownerId' => self::USER]);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B', 'ownerId' => self::USER]);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $a, 'ownerId' => self::USER]);
        $kept = $this->createCollection(['workspace' => $workspace, 'name' => 'Kept', 'ownerId' => self::USER]);
        $asset = $this->createAsset(['workspace' => $workspace, 'collectionId' => $a->getId(), 'ownerId' => self::USER]);
        foreach ([$a, $b, $child, $kept] as $c) {
            $this->setCollectionPrivacy($c, Privacy::PUBLIC_IN_WORKSPACE);
        }

        $response = $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => [
                'ids' => [$a->getId(), $b->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame('', $response->getContent());

        // Soft delete only: nothing is removed
        foreach ([$a, $b] as $c) {
            $this->assertNotNull($this->findCollection($c->getId())->getDeletedAt(), $c->getName().' should be in the trash');
        }
        $this->assertNull($this->findCollection($child->getId())->getDeletedAt());
        $this->assertTrue($this->findCollection($child->getId())->isDeleted());
        $this->assertNull($this->findCollection($kept->getId())->getDeletedAt());
        $this->assertNotNull($this->findAssetById($asset->getId()));

        // The owner still reads it, others no longer do
        $this->request('GET', '/collections/'.$a->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['deleted' => true]);
        $this->request('GET', '/collections/'.$a->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/collections/'.$child->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/collections/'.$kept->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(200);

        $this->request('POST', '/collections/restore-multiple', self::USER, [
            'json' => [
                'ids' => [$a->getId(), $b->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        foreach ([$a, $b, $child] as $c) {
            $this->assertFalse($this->findCollection($c->getId())->isDeleted(), $c->getName().' should be restored');
        }
        $this->request('GET', '/collections/'.$child->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['deleted' => false]);
    }

    public function testHardDeleteOfTrashedCollection(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $a]);
        $asset = $this->createAsset(['workspace' => $workspace, 'collectionId' => $child->getId()]);

        $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => ['ids' => [$a->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => ['ids' => [$a->getId()], 'hardDelete' => true],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull($this->findCollection($a->getId()));
        $this->assertNull($this->findCollection($child->getId()));
        $this->assertNull($this->findAssetById($asset->getId()));
    }

    public function testHardDeleteOnlyPurgesTrashedCollections(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);

        $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => ['ids' => [$a->getId()], 'hardDelete' => true],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // The collection was not in the trash: the deletion job ignores it
        $collection = $this->findCollection($a->getId());
        $this->assertNotNull($collection);
        $this->assertNull($collection->getDeletedAt());
    }

    public static function getPermissionMatrix(): iterable
    {
        yield 'collection owner' => ['owner', 204];
        yield 'reader' => [PermissionInterface::VIEW, 403];
        yield 'editor' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 403];
        yield 'acl delete' => [PermissionInterface::VIEW | PermissionInterface::DELETE, 204];
        yield 'non member' => ['non-member', 403];
        yield 'anonymous' => ['anonymous', 401];
        yield 'admin' => ['admin', 204];
    }

    #[DataProvider('getPermissionMatrix')]
    public function testDeleteAndRestorePermissions(int|string $grant, int $expectedCode): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'ownerId' => 'owner' === $grant ? self::USER : 'coll_owner',
        ]);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC_IN_WORKSPACE);

        $userId = self::USER;
        if (is_int($grant)) {
            $this->grantUserOnObject(self::USER, $collection, $grant);
        } elseif ('non-member' === $grant) {
            $userId = self::OTHER;
        } elseif ('anonymous' === $grant) {
            $userId = self::ANONYMOUS;
        } elseif ('admin' === $grant) {
            $userId = self::ADMIN;
        }

        $this->request('POST', '/collections/delete-multiple', $userId, [
            'json' => ['ids' => [$collection->getId()]],
        ]);
        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(204 === $expectedCode, null !== $this->findCollection($collection->getId())->getDeletedAt());

        // Restoring requires the same permission
        $collection = $this->findCollection($collection->getId());
        $collection->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->request('POST', '/collections/restore-multiple', $userId, [
            'json' => ['ids' => [$collection->getId()]],
        ]);
        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(204 !== $expectedCode, null !== $this->findCollection($collection->getId())->getDeletedAt());
    }

    public function testOneForbiddenCollectionDeniesTheWholeBatch(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $mine = $this->createCollection(['workspace' => $workspace, 'name' => 'Mine', 'ownerId' => self::USER]);
        $notMine = $this->createCollection(['workspace' => $workspace, 'name' => 'Not mine']);
        $this->setCollectionPrivacy($notMine, Privacy::PUBLIC_IN_WORKSPACE);

        $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => ['ids' => [$mine->getId(), $notMine->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findCollection($mine->getId())->getDeletedAt());
        $this->assertNull($this->findCollection($notMine->getId())->getDeletedAt());
    }

    public function testInvalidIds(): void
    {
        foreach (['delete-multiple', 'restore-multiple'] as $action) {
            foreach ([[], ['ids' => []], ['ids' => null]] as $payload) {
                $this->request('POST', '/collections/'.$action, self::USER, [
                    'json' => $payload,
                ]);
                $this->assertResponseStatusCodeSame(422, $action.' '.json_encode($payload));
                $this->assertJsonContains([
                    'violations' => [['propertyPath' => 'ids']],
                ]);
            }
        }
    }

    public function testUnknownIds(): void
    {
        $this->markTestIncomplete('BUG: unknown IDs are skipped by the permission check of CollectionsDeleteProcessor/CollectionsRestoreProcessor (DoctrineUtil::iterateIds), then CollectionsMoveToTrashHandler (src/Consumer/Handler/Collection/CollectionsMoveToTrashHandler.php:26) and CollectionsRestoreHandler (:26) call getAbsolutePath() on null: 500 (and a failing job in production)');

        foreach (['delete-multiple', 'restore-multiple'] as $action) {
            $this->request('POST', '/collections/'.$action, self::USER, [
                'json' => ['ids' => ['00000000-0000-4000-8000-000000000000']],
            ]);
            $this->assertResponseStatusCodeSame(404, $action);
        }
    }
}
