<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * POST /collection-assets, DELETE /collection-assets/{id}
 * (and the guards of GET /collections/{id}/assets, its listing being covered by CollectionAssetPositionTest).
 */
final class CollectionAssetRelationTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    public function testAddAssetToCollection(): void
    {
        [$collection, $asset] = $this->createFixtures();
        $this->grantUserOnObject(self::USER, $collection, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);

        $response = $this->addRelation($collection, $asset, self::USER);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('collection-asset', $data['@type']);
        $this->assertMatchesUuid($data['id']);
        $this->assertSame(0, $data['position']);
        $this->assertSame('/assets/'.$asset->getId(), $data['asset']['@id']);

        $relation = $this->findCollectionAsset($data['id']);
        $this->assertSame($collection->getId(), $relation->getCollection()->getId());
        $this->assertSame($asset->getId(), $relation->getAsset()->getId());
        // The reference collection of the asset is unchanged
        $this->assertNull($this->findAssetById($asset->getId())->getReferenceCollection());

        // Appended at the end
        $second = $this->createAsset([
            'workspace' => self::getEntityManager()->find(Workspace::class, $collection->getWorkspaceId()),
            'ownerId' => self::USER,
        ]);
        $response = $this->addRelation($collection, $second, self::USER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(1, $response->toArray()['position']);
    }

    public static function getCreateMatrix(): iterable
    {
        yield 'reader' => [PermissionInterface::VIEW, 403];
        yield 'collection editor' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 403];
        // OWNER does not imply CHILD_CREATE
        yield 'collection acl owner' => [PermissionInterface::OWNER, 403];
        yield 'child create' => [PermissionInterface::CHILD_CREATE, 201];
        yield 'child create on parent' => ['parent', 201];
        yield 'collection owner' => ['owner', 403];
        yield 'workspace owner' => ['workspace owner', 201];
        yield 'admin' => ['admin', 201];
        yield 'anonymous' => ['anonymous', 401];
    }

    /**
     * @dataProvider getCreateMatrix
     */
    public function testAddRequiresAssetCreateOnCollection(int|string $grant, int $expectedCode): void
    {
        [$collection, $asset] = $this->createFixtures([
            'collectionOwner' => 'owner' === $grant ? self::USER : 'coll_owner',
            'withParent' => true,
        ]);

        $userId = self::USER;
        if (is_int($grant)) {
            $this->grantUserOnObject(self::USER, $collection, $grant);
        } elseif ('parent' === $grant) {
            $this->grantUserOnObject(self::USER, $collection->getParent(), PermissionInterface::CHILD_CREATE);
        } elseif ('workspace owner' === $grant) {
            $workspace = $collection->getWorkspace();
            $workspace->setOwnerId(self::USER);
            self::getEntityManager()->flush();
        } elseif ('admin' === $grant) {
            $userId = self::ADMIN;
        } elseif ('anonymous' === $grant) {
            $userId = self::ANONYMOUS;
        }

        $this->addRelation($collection, $asset, $userId);
        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(201 === $expectedCode ? 1 : 0, $this->countRelations($collection));
    }

    public function testAddRequiresReadOnAsset(): void
    {
        [$collection] = $this->createFixtures();
        $this->grantUserOnObject(self::USER, $collection, PermissionInterface::CHILD_CREATE);
        $hidden = $this->createAsset(['workspace' => $collection->getWorkspace(), 'ownerId' => 'someone_else']);

        $this->addRelation($collection, $hidden, self::USER);
        $this->assertResponseStatusCodeSame(403);

        $hidden->setPrivacy(Privacy::PUBLIC_IN_WORKSPACE);
        self::getEntityManager()->flush();
        $this->addRelation($collection, $hidden, self::USER);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAddTwiceIsRejected(): void
    {
        [$collection, $asset] = $this->createFixtures();
        $this->grantUserOnObject(self::USER, $collection, PermissionInterface::CHILD_CREATE);

        $this->addRelation($collection, $asset, self::USER);
        $this->assertResponseStatusCodeSame(201);
        $this->addRelation($collection, $asset, self::USER);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [['message' => 'This asset is already part of the collection.']],
        ]);
        $this->assertSame(1, $this->countRelations($collection));
    }

    public function testAddAssetOfAnotherWorkspaceIsRejected(): void
    {
        [$collection] = $this->createFixtures();
        $otherWorkspace = $this->createTestWorkspace(['ownerId' => self::ADMIN]);
        $foreign = $this->createAsset(['workspace' => $otherWorkspace, 'ownerId' => self::ADMIN]);

        $this->addRelation($collection, $foreign, self::ADMIN);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countRelations($collection));
    }

    public function testAddWithUnknownCollection(): void
    {
        [, $asset] = $this->createFixtures();

        $this->request('POST', '/collection-assets', self::ADMIN, [
            'json' => [
                'collection' => '/collections/00000000-0000-4000-8000-000000000000',
                'asset' => '/assets/'.$asset->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddWithMissingProperties(): void
    {
        $this->markTestIncomplete('BUG: securityPostDenormalize runs CollectionAssetVoter (src/Security/Voter/CollectionAssetVoter.php:35) before validation, and CollectionAsset::getAsset()/getCollection() are not nullable: a payload without "asset" or "collection" gives a 500 TypeError instead of a 422');

        [$collection, $asset] = $this->createFixtures();

        foreach ([
            ['collection' => '/collections/'.$collection->getId()],
            ['asset' => '/assets/'.$asset->getId()],
            [],
        ] as $payload) {
            $this->request('POST', '/collection-assets', self::ADMIN, [
                'json' => $payload,
            ]);
            $this->assertResponseStatusCodeSame(422, json_encode($payload));
        }
    }

    public static function getDeleteMatrix(): iterable
    {
        yield 'asset owner' => ['asset owner', 204];
        yield 'reader' => [PermissionInterface::VIEW, 403];
        yield 'collection editor' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 403];
        yield 'collection delete' => [PermissionInterface::DELETE, 204];
        yield 'child delete' => [PermissionInterface::CHILD_DELETE, 204];
        yield 'collection owner' => ['collection owner', 204];
        yield 'acl owner on asset' => ['asset acl', 204];
        yield 'non member' => ['non-member', 403];
        yield 'anonymous' => ['anonymous', 401];
        yield 'admin' => ['admin', 204];
    }

    /**
     * @dataProvider getDeleteMatrix
     */
    public function testRemoveAssetFromCollection(int|string $grant, int $expectedCode): void
    {
        [$collection, $asset] = $this->createFixtures([
            'collectionOwner' => 'collection owner' === $grant ? self::USER : 'coll_owner',
            'assetOwner' => 'asset owner' === $grant ? self::USER : 'asset_owner',
        ]);
        $relationId = $this->addAssetToCollection($collection->getId(), $asset->getId());

        $userId = self::USER;
        if (is_int($grant)) {
            $this->grantUserOnObject(self::USER, $collection, $grant);
        } elseif ('asset acl' === $grant) {
            $this->grantUserOnObject(self::USER, $asset, PermissionInterface::OWNER);
        } elseif ('non-member' === $grant) {
            $userId = self::OTHER;
        } elseif ('anonymous' === $grant) {
            $userId = self::ANONYMOUS;
        } elseif ('admin' === $grant) {
            $userId = self::ADMIN;
        }

        $this->request('DELETE', '/collection-assets/'.$relationId, $userId);
        $this->assertResponseStatusCodeSame($expectedCode);

        if (204 === $expectedCode) {
            $this->assertNull($this->findCollectionAsset($relationId));
        } else {
            $this->assertNotNull($this->findCollectionAsset($relationId));
        }
        // Only the relation is removed
        $this->assertNotNull($this->findAssetById($asset->getId()));
        $this->assertNotNull($this->findCollection($collection->getId()));
    }

    public function testAssetOwnerMayRemoveItFromAnyCollection(): void
    {
        // Even from a collection they cannot read
        [$collection, $asset] = $this->createFixtures(['assetOwner' => self::USER]);
        $secret = $this->createCollection(['workspace' => $collection->getWorkspace(), 'name' => 'Secret']);
        $relationId = $this->addAssetToCollection($secret->getId(), $asset->getId());

        $this->request('GET', '/collections/'.$secret->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('DELETE', '/collection-assets/'.$relationId, self::USER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testRemoveUnknownRelation(): void
    {
        $this->request('DELETE', '/collection-assets/00000000-0000-4000-8000-000000000000', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testThereIsNoItemReadOperation(): void
    {
        [$collection, $asset] = $this->createFixtures();
        $relationId = $this->addAssetToCollection($collection->getId(), $asset->getId());

        $this->request('GET', '/collection-assets/'.$relationId, self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
        $this->request('GET', '/collection-assets', self::ADMIN);
        $this->assertResponseStatusCodeSame(405);
    }

    public function testListAssetsOfUnknownCollection(): void
    {
        $this->request('GET', '/collections/00000000-0000-4000-8000-000000000000/assets', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListAssetsRequiresReadOnCollection(): void
    {
        [$collection] = $this->createFixtures();

        $this->request('GET', '/collections/'.$collection->getId().'/assets', self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/collections/'.$collection->getId().'/assets', self::ANONYMOUS);
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * USER is a member of the workspace and owns the asset by default.
     *
     * @return array{Collection, Asset}
     */
    private function createFixtures(array $options = []): array
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = null;
        if ($options['withParent'] ?? false) {
            $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        }
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Target',
            'ownerId' => $options['collectionOwner'] ?? 'coll_owner',
            'parent' => $parent,
        ]);
        $this->setCollectionPrivacy($collection, Privacy::PRIVATE_IN_WORKSPACE);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => $options['assetOwner'] ?? self::USER,
        ]);

        return [$collection, $asset];
    }

    private function addRelation(Collection $collection, Asset $asset, string $userId)
    {
        return $this->request('POST', '/collection-assets', $userId, [
            'json' => [
                'collection' => '/collections/'.$collection->getId(),
                'asset' => '/assets/'.$asset->getId(),
            ],
        ]);
    }

    private function countRelations(Collection $collection): int
    {
        return self::getEntityManager()->getRepository(CollectionAsset::class)->count([
            'collection' => $collection->getId(),
        ]);
    }
}
