<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * POST /collections, PUT/PATCH/DELETE /collections/{id}.
 */
final class CollectionWriteTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    public function testWorkspaceOwnerCreatesRootCollection(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'collection',
            'name' => 'Root',
            'privacy' => Privacy::SECRET,
            'absoluteName' => 'Root',
        ]);

        $collection = $this->findCollection($response->toArray()['id']);
        $this->assertSame(self::USER, $collection->getOwnerId());
        $this->assertSame($workspace->getId(), $collection->getWorkspaceId());
        $this->assertNull($collection->getParent());
    }

    public function testCreateRootCollectionWithCreateCollectionPermission(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::OTHER]]);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | PermissionInterface::CREATE);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateRootCollectionRequiresCreateCollectionPermission(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotCreateCollection(): void
    {
        $workspace = $this->createTestWorkspace();

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnonymousCannotCreateCollection(): void
    {
        $workspace = $this->createTestWorkspace(['public' => true]);

        // POST /collections has no "security" expression: the anonymous request
        // reaches the input transformer, which requires an owner...
        $this->request('POST', '/collections', self::ANONYMOUS, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'You must provide "ownerId" as your access token is not associated to a user.']);

        // ...then the voter denies the creation
        $this->request('POST', '/collections', self::ANONYMOUS, [
            'json' => [
                'name' => 'Root',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'ownerId' => self::USER,
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(0, self::getEntityManager()->getRepository(Collection::class)->count([]));
    }

    public function testCreateChildInheritsWorkspaceFromParent(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Child',
                'parent' => '/collections/'.$parent->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame($parent->getId(), $data['parentId']);
        $this->assertSame('/'.$parent->getId().'/'.$data['id'], $data['absolutePath']);
        $this->assertSame('Parent'.self::SEP.'Child', $data['absoluteName']);
        $this->assertSame($workspace->getId(), $this->findCollection($data['id'])->getWorkspaceId());
    }

    public function testCreateChildWithAclCreateOnParent(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $this->grantUserOnObject(self::USER, $parent, PermissionInterface::VIEW | PermissionInterface::CREATE);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Child',
                'parent' => '/collections/'.$parent->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateChildRequiresCreatePermissionOnParent(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret parent']);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Child',
                'parent' => '/collections/'.$parent->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateChildInAnotherWorkspaceIsRejected(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $otherWorkspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $parent = $this->createCollection(['workspace' => $otherWorkspace, 'name' => 'Parent']);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Child',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'parent' => '/collections/'.$parent->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Cannot add a sub-collection in a different workspace']);
    }

    public function testCreateWithoutWorkspace(): void
    {
        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Lost',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Missing workspace']);
    }

    public function testCreateWithUnknownWorkspace(): void
    {
        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Lost',
                'workspace' => '/workspaces/00000000-0000-4000-8000-000000000000',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateWithBlankName(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        foreach ([null, ''] as $name) {
            $this->request('POST', '/collections', self::USER, [
                'json' => [
                    'name' => $name,
                    'workspace' => '/workspaces/'.$workspace->getId(),
                ],
            ]);
            $this->assertResponseStatusCodeSame(422);
            $this->assertJsonContains([
                'violations' => [['propertyPath' => 'name']],
            ]);
        }
    }

    public function testCreateWithTooLongName(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => str_repeat('a', 256),
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [['propertyPath' => 'name']],
        ]);
    }

    public function testPrivacyIsSetByWhomMayEditPermissions(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Public',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'privacy' => Privacy::PUBLIC,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(Privacy::PUBLIC, $response->toArray()['privacy']);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'By label',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'privacyLabel' => 'public_in_workspace',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(Privacy::PUBLIC_IN_WORKSPACE, $response->toArray()['privacy']);
    }

    public function testInvalidPrivacyLabel(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'C',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'privacyLabel' => 'top_secret',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Invalid privacyLabel "top_secret"']);
    }

    public function testOutOfRangePrivacyIsRejected(): void
    {
        $this->markTestIncomplete('BUG: no validation of the privacy value (src/Api/InputTransformer/AbstractInputTransformer.php:33 and WorkspacePrivacyTrait::setPrivacy()): any integer is stored');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'C',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'privacy' => 42,
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testPrivacyIsIgnoredWithoutEditPermissions(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $this->grantUserOnObject(self::USER, $parent, PermissionInterface::VIEW | PermissionInterface::CREATE | PermissionInterface::EDIT);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Child',
                'parent' => '/collections/'.$parent->getId(),
                'privacy' => Privacy::PUBLIC,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(Privacy::SECRET, $response->toArray()['privacy']);

        $this->request('PUT', '/collections/'.$parent->getId(), self::USER, [
            'json' => [
                'name' => 'Parent',
                'privacy' => Privacy::PUBLIC,
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['privacy' => Privacy::SECRET]);
        $this->assertSame(Privacy::SECRET, $this->findCollection($parent->getId())->getPrivacy());
    }

    public function testCreateWithKeyReusesExistingCollection(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'First',
                'key' => 'my-key',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Renamed',
                'key' => 'my-key',
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($id, $response->toArray()['id']);
        $this->assertSame(1, self::getEntityManager()->getRepository(Collection::class)->count(['workspace' => $workspace->getId()]));

        // The same key in another workspace is another collection
        $otherWorkspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Elsewhere',
                'key' => 'my-key',
                'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertNotSame($id, $response->toArray()['id']);
    }

    public function testCreateWithKeyUpdatesExistingCollection(): void
    {
        $this->markTestIncomplete('BUG: CollectionInputTransformer (src/Api/InputTransformer/CollectionInputTransformer.php:30) sets the name on the new Collection before swapping it for the one found by key: the upsert updates privacy, translations or extraMetadata, but never the name');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'First']);
        $collection->setKey('my-key');
        self::getEntityManager()->flush();

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'Renamed',
                'key' => 'my-key',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'extraMetadata' => ['updated' => true],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $updated = $this->findCollection($collection->getId());
        $this->assertSame(['updated' => true], $updated->getExtraMetadata());
        $this->assertSame('Renamed', $updated->getName());
    }

    public function testCreateWithTranslationsAndExtraMetadata(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $response = $this->request('POST', '/collections', self::USER, [
            'headers' => ['Accept-Language' => 'de'],
            'json' => [
                'name' => 'Holidays',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'translations' => ['name' => ['de' => 'Urlaub', 'fr' => '']],
                'extraMetadata' => ['color' => 'red'],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'Holidays',
            'displayName' => 'Urlaub',
            // empty translations are dropped
            'translations' => ['name' => ['de' => 'Urlaub']],
            'extraMetadata' => ['color' => 'red'],
        ]);
        $this->assertArrayNotHasKey('fr', $response->toArray()['translations']['name']);
    }

    public function testCreateWithExplicitOwner(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);

        $response = $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'For other',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'ownerId' => self::OTHER,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(self::OTHER, $this->findCollection($response->toArray()['id'])->getOwnerId());
    }

    public function testPut(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Old', 'ownerId' => self::USER]);

        $this->request('PUT', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'name' => 'New',
                'translations' => ['name' => ['fr' => 'Nouveau']],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'id' => $collection->getId(),
            'name' => 'New',
        ]);
        $updated = $this->findCollection($collection->getId());
        $this->assertSame('New', $updated->getName());
        $this->assertSame(['name' => ['fr' => 'Nouveau']], $updated->getTranslations());
        $this->assertSame(self::USER, $updated->getOwnerId());
    }

    public function testPutWithBlankName(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Old', 'ownerId' => self::USER]);

        $this->request('PUT', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'name' => '',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Old', $this->findCollection($collection->getId())->getName());
    }

    public function testPatchRename(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Old', 'ownerId' => self::USER]);

        $this->request('PATCH', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'name' => 'Patched',
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Patched']);
        $this->assertSame('Patched', $this->findCollection($collection->getId())->getName());
    }

    public function testPatchIsPartial(): void
    {
        $this->markTestIncomplete('BUG: PATCH is not partial: CollectionInputTransformer (src/Api/InputTransformer/CollectionInputTransformer.php:30) always calls setName($data->name), so a PATCH without "name" resets it and fails with a 422');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Kept']);

        $this->request('PATCH', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'privacy' => Privacy::PUBLIC,
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Kept', 'privacy' => Privacy::PUBLIC]);
    }

    public function testCannotChangeParentThroughPut(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);
        $other = $this->createCollection(['workspace' => $workspace, 'name' => 'Other']);

        $this->request('PUT', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'name' => 'C',
                'parent' => '/collections/'.$other->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'hydra:description' => sprintf('Cannot change parent. Use POST /collections/%s/move', $collection->getId()),
        ]);
        $this->assertNull($this->findCollection($collection->getId())->getParent());
    }

    public function testWorkspaceIsNotChangedByPut(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $otherWorkspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);

        $this->request('PUT', '/collections/'.$collection->getId(), self::USER, [
            'json' => [
                'name' => 'C',
                'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame($workspace->getId(), $this->findCollection($collection->getId())->getWorkspaceId());
    }

    public static function getEditMatrix(): iterable
    {
        yield 'collection owner' => ['owner', 200];
        yield 'reader' => [PermissionInterface::VIEW, 403];
        yield 'acl edit' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 200];
        yield 'acl owner' => [PermissionInterface::OWNER, 200];
        yield 'acl edit on parent' => ['parent', 200];
        yield 'non member' => ['non-member', 403];
        yield 'anonymous' => ['anonymous', 401];
        yield 'admin' => ['admin', 200];
    }

    /**
     * @dataProvider getEditMatrix
     */
    public function testEditPermissions(int|string $grant, int $expectedCode): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'parent' => $parent,
            'ownerId' => 'owner' === $grant ? self::USER : 'coll_owner',
        ]);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC_IN_WORKSPACE);

        $userId = self::USER;
        if (is_int($grant)) {
            $this->grantUserOnObject(self::USER, $collection, $grant);
        } elseif ('parent' === $grant) {
            $this->grantUserOnObject(self::USER, $parent, PermissionInterface::EDIT);
        } elseif ('non-member' === $grant) {
            $userId = self::OTHER;
        } elseif ('anonymous' === $grant) {
            $userId = self::ANONYMOUS;
        } elseif ('admin' === $grant) {
            $userId = self::ADMIN;
        }

        foreach (['PUT', 'PATCH'] as $method) {
            $this->request($method, '/collections/'.$collection->getId(), $userId, [
                'json' => [
                    'name' => 'Renamed by '.$method,
                ],
            ]);
            $this->assertResponseStatusCodeSame($expectedCode, $method);
        }

        $this->assertSame(
            200 === $expectedCode ? 'Renamed by PATCH' : 'C',
            $this->findCollection($collection->getId())->getName()
        );
    }

    public static function getDeleteMatrix(): iterable
    {
        yield 'collection owner' => ['owner', 204];
        yield 'reader' => [PermissionInterface::VIEW, 403];
        yield 'editor' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 403];
        yield 'acl delete' => [PermissionInterface::VIEW | PermissionInterface::DELETE, 204];
        yield 'acl delete on parent' => ['parent', 204];
        yield 'non member' => ['non-member', 403];
        yield 'anonymous' => ['anonymous', 401];
        yield 'admin' => ['admin', 204];
    }

    /**
     * @dataProvider getDeleteMatrix
     */
    public function testDeletePermissions(int|string $grant, int $expectedCode): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'parent' => $parent,
            'ownerId' => 'owner' === $grant ? self::USER : 'coll_owner',
        ]);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC_IN_WORKSPACE);

        $userId = self::USER;
        if (is_int($grant)) {
            $this->grantUserOnObject(self::USER, $collection, $grant);
        } elseif ('parent' === $grant) {
            $this->grantUserOnObject(self::USER, $parent, PermissionInterface::DELETE);
        } elseif ('non-member' === $grant) {
            $userId = self::OTHER;
        } elseif ('anonymous' === $grant) {
            $userId = self::ANONYMOUS;
        } elseif ('admin' === $grant) {
            $userId = self::ADMIN;
        }

        $this->request('DELETE', '/collections/'.$collection->getId(), $userId);
        $this->assertResponseStatusCodeSame($expectedCode);

        if (204 === $expectedCode) {
            $this->assertNull($this->findCollection($collection->getId()));
        } else {
            $this->assertNotNull($this->findCollection($collection->getId()));
        }
        $this->assertNotNull($this->findCollection($parent->getId()));
    }

    public function testDeleteCascadesToChildrenAndReferencedAssets(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root]);
        $grandChild = $this->createCollection(['workspace' => $workspace, 'name' => 'GrandChild', 'parent' => $child]);
        $sibling = $this->createCollection(['workspace' => $workspace, 'name' => 'Sibling']);

        $inRoot = $this->createAsset(['workspace' => $workspace, 'collectionId' => $root->getId()]);
        $inGrandChild = $this->createAsset(['workspace' => $workspace, 'collectionId' => $grandChild->getId()]);
        // Referenced by the sibling, only linked to the deleted branch
        $linked = $this->createAsset(['workspace' => $workspace, 'collectionId' => $sibling->getId()]);
        $linkId = $this->addAssetToCollection($child->getId(), $linked->getId());
        $siblingLinkId = $this->addAssetToCollection($sibling->getId(), $inRoot->getId());

        $this->request('DELETE', '/collections/'.$root->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        foreach ([$root, $child, $grandChild] as $c) {
            $this->assertNull($this->findCollection($c->getId()), $c->getName().' should be deleted');
        }
        $this->assertNotNull($this->findCollection($sibling->getId()));

        // Assets whose reference collection is in the branch are deleted...
        $this->assertNull($this->findAssetById($inRoot->getId()));
        $this->assertNull($this->findAssetById($inGrandChild->getId()));
        $this->assertNull($this->findCollectionAsset($siblingLinkId));
        // ...while assets only linked to it are kept, without the link
        $this->assertNotNull($this->findAssetById($linked->getId()));
        $this->assertNull($this->findCollectionAsset($linkId));
        $this->assertSame(1, self::getEntityManager()->getRepository(CollectionAsset::class)->count([
            'asset' => $linked->getId(),
        ]));
    }

    public function testDeleteUnknownCollection(): void
    {
        $this->request('DELETE', '/collections/00000000-0000-4000-8000-000000000000', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteDoesNotTouchOtherWorkspaces(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $otherWorkspace = $this->createTestWorkspace();
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);
        $other = $this->createCollection(['workspace' => $otherWorkspace, 'name' => 'Other']);
        $asset = $this->createAsset(['workspace' => $otherWorkspace, 'collectionId' => $other->getId()]);

        $this->request('DELETE', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNotNull($this->findCollection($other->getId()));
        $this->assertNotNull(self::getEntityManager()->find(Asset::class, $asset->getId()));
    }
}
