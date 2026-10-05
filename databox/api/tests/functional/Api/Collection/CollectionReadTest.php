<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /collections/{id}, /collections/{id}/ascendants and /collections/{id}/privacy.
 */
final class CollectionReadTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    /**
     * USER is a member of the (private) workspace, OTHER is not.
     */
    public static function getReadMatrix(): iterable
    {
        foreach ([
            'secret' => [Privacy::SECRET, 403, 403, 401],
            'private_in_workspace' => [Privacy::PRIVATE_IN_WORKSPACE, 200, 403, 401],
            'public_in_workspace' => [Privacy::PUBLIC_IN_WORKSPACE, 200, 403, 401],
            // "listed to every user", yet the workspace itself is not readable by OTHER
            'private' => [Privacy::PRIVATE, 200, 403, 401],
            'public_for_users' => [Privacy::PUBLIC_FOR_USERS, 200, 403, 401],
            'public' => [Privacy::PUBLIC, 200, 403, 401],
        ] as $name => [$privacy, $member, $other, $anonymous]) {
            yield $name.' / member' => [$privacy, self::USER, $member];
            yield $name.' / non-member' => [$privacy, self::OTHER, $other];
            yield $name.' / anonymous' => [$privacy, self::ANONYMOUS, $anonymous];
            yield $name.' / admin' => [$privacy, self::ADMIN, 200];
            yield $name.' / workspace owner' => [$privacy, self::WS_OWNER, 200];
        }
    }

    /**
     * @dataProvider getReadMatrix
     */
    public function testReadDependsOnPrivacyInPrivateWorkspace(int $privacy, string $userId, int $expectedCode): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'ownerId' => 'coll_owner',
        ]);
        $this->setCollectionPrivacy($collection, $privacy);

        if (self::WS_OWNER === $userId) {
            // ws_owner is not a Keycloak mock user: reuse OTHER as the workspace owner
            $workspace->setOwnerId(self::OTHER);
            self::getEntityManager()->flush();
            $userId = self::OTHER;
        }

        $this->request('GET', '/collections/'.$collection->getId(), $userId);
        $this->assertResponseStatusCodeSame($expectedCode);
    }

    public static function getPublicWorkspaceReadMatrix(): iterable
    {
        yield 'secret / non-member' => [Privacy::SECRET, self::OTHER, 403];
        yield 'private_in_workspace / non-member' => [Privacy::PRIVATE_IN_WORKSPACE, self::OTHER, 200];
        yield 'private / non-member' => [Privacy::PRIVATE, self::OTHER, 200];
        yield 'secret / anonymous' => [Privacy::SECRET, self::ANONYMOUS, 401];
        yield 'public / anonymous' => [Privacy::PUBLIC, self::ANONYMOUS, 200];
    }

    /**
     * @dataProvider getPublicWorkspaceReadMatrix
     */
    public function testReadDependsOnPrivacyInPublicWorkspace(int $privacy, string $userId, int $expectedCode): void
    {
        $workspace = $this->createTestWorkspace(['public' => true]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'ownerId' => 'coll_owner',
        ]);
        $this->setCollectionPrivacy($collection, $privacy);

        $this->request('GET', '/collections/'.$collection->getId(), $userId);
        $this->assertResponseStatusCodeSame($expectedCode);
    }

    public function testAnonymousCannotReadNonPublicCollectionOfPublicWorkspace(): void
    {
        $this->markTestIncomplete('BUG: CollectionVoter READ (src/Security/Voter/CollectionVoter.php:92) grants any privacy >= PRIVATE_IN_WORKSPACE without checking the user, which makes the "$userId &&" guard of line 91 dead code: anonymous users read PRIVATE_IN_WORKSPACE/PRIVATE/PUBLIC_FOR_USERS collections of a public workspace, whereas the search only lists PUBLIC ones to them');

        $workspace = $this->createTestWorkspace(['public' => true]);
        foreach ([Privacy::PRIVATE_IN_WORKSPACE, Privacy::PRIVATE, Privacy::PUBLIC_FOR_USERS] as $privacy) {
            $collection = $this->createCollection([
                'workspace' => $workspace,
                'name' => 'C'.$privacy,
                'ownerId' => 'coll_owner',
            ]);
            $this->setCollectionPrivacy($collection, $privacy);

            $this->request('GET', '/collections/'.$collection->getId(), self::ANONYMOUS);
            $this->assertResponseStatusCodeSame(401);
        }
    }

    public function testOwnerCannotReadWithoutWorkspaceAccess(): void
    {
        $workspace = $this->createTestWorkspace();
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'ownerId' => self::USER,
            'name' => 'Mine',
        ]);

        $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        $this->addUserOnWorkspace(self::USER, $workspace->getId());
        $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAclViewGrantsReadOnSecretCollection(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret']);

        $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::USER, $collection, PermissionInterface::VIEW);
        $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testReadablePrivacyOfParentIsInheritedBySecretChildren(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $parent]);
        $grandChild = $this->createCollection(['workspace' => $workspace, 'name' => 'GrandChild', 'parent' => $child]);

        $this->request('GET', '/collections/'.$grandChild->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        $this->setCollectionPrivacy($parent, Privacy::PUBLIC_IN_WORKSPACE);

        $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $response = $this->request('GET', '/collections/'.$grandChild->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'privacy' => Privacy::SECRET,
            'inheritedPrivacy' => Privacy::PUBLIC_IN_WORKSPACE,
            'public' => true,
        ]);
        $this->assertSame($child->getId(), $response->toArray()['parentId']);
    }

    public function testAclOnParentGrantsReadOnDescendants(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $parent]);
        $sibling = $this->createCollection(['workspace' => $workspace, 'name' => 'Sibling']);

        $this->grantUserOnObject(self::USER, $parent, PermissionInterface::VIEW);

        $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['shared' => true]);
        $this->request('GET', '/collections/'.$sibling->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testParentIdIsHiddenWhenParentIsNotReadable(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $parent]);
        $this->grantUserOnObject(self::USER, $child, PermissionInterface::VIEW);

        $response = $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertNull($data['parentId'] ?? null);
        // The absolute path still exposes the full branch
        $this->assertSame('/'.$parent->getId().'/'.$child->getId(), $data['absolutePath']);
        $this->assertSame('Parent'.self::SEP.'Child', $data['absoluteName']);
    }

    public function testNotFound(): void
    {
        $this->request('GET', '/collections/00000000-0000-4000-8000-000000000000', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutputShape(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root', 'ownerId' => self::USER]);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root, 'ownerId' => self::USER]);
        $child->setTranslations(['name' => ['fr' => 'Enfant', 'en' => 'Child EN']]);
        $child->setExtraMetadata(['foo' => 'bar']);
        $em = self::getEntityManager();
        $em->flush();

        $response = $this->request('GET', '/collections/'.$child->getId(), self::USER, [
            'headers' => ['Accept-Language' => 'fr'],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'collection',
            'id' => $child->getId(),
            'name' => 'Child',
            'displayName' => 'Enfant',
            'absoluteName' => 'Root'.self::SEP.'Child',
            'absoluteDisplayName' => 'Root'.self::SEP.'Enfant',
            'absolutePath' => '/'.$root->getId().'/'.$child->getId(),
            'parentId' => $root->getId(),
            'privacy' => Privacy::SECRET,
            'inheritedPrivacy' => Privacy::SECRET,
            'shared' => false,
            'public' => false,
            'deleted' => false,
            'extraMetadata' => ['foo' => 'bar'],
            'translations' => ['name' => ['fr' => 'Enfant', 'en' => 'Child EN']],
            'topicSubscriptions' => [],
            'capabilities' => [
                'edit' => true,
                'delete' => true,
                // Ownership alone does not grant those
                'editPermissions' => false,
                'createAsset' => false,
                'createCollection' => true,
            ],
        ]);
        $data = $response->toArray();
        $this->assertSame('/workspaces/'.$workspace->getId(), $data['workspace']['@id']);
        $this->assertArrayHasKey('owner', $data);
        // GET item does not embed children (only the list operation does)
        $this->assertArrayNotHasKey('children', $data);

        $response = $this->request('GET', '/collections/'.$root->getId(), self::USER, [
            'headers' => ['Accept-Language' => 'en'],
        ]);
        $data = $response->toArray();
        $this->assertSame('/'.$root->getId(), $data['absolutePath']);
        $this->assertSame('Root', $data['absoluteName']);
        $this->assertNull($data['inheritedPrivacy'] ?? null);
        $this->assertNull($data['parentId'] ?? null);
    }

    public static function getCapabilitiesMatrix(): iterable
    {
        yield 'reader only' => [PermissionInterface::VIEW, [
            'edit' => false,
            'delete' => false,
            'editPermissions' => false,
            'createAsset' => false,
            'createCollection' => false,
        ]];
        yield 'editor' => [PermissionInterface::VIEW | PermissionInterface::EDIT, [
            'edit' => true,
            'delete' => false,
            'editPermissions' => false,
            'createAsset' => false,
            'createCollection' => false,
        ]];
        yield 'deleter' => [PermissionInterface::VIEW | PermissionInterface::DELETE, [
            'edit' => false,
            'delete' => true,
            'editPermissions' => false,
            'createAsset' => false,
            'createCollection' => false,
        ]];
        yield 'creator' => [PermissionInterface::VIEW | PermissionInterface::CREATE | PermissionInterface::CHILD_CREATE, [
            'edit' => false,
            'delete' => false,
            'editPermissions' => false,
            'createAsset' => true,
            'createCollection' => true,
        ]];
        // OWNER does not imply the CHILD_* permissions
        yield 'owner' => [PermissionInterface::OWNER, [
            'edit' => true,
            'delete' => true,
            'editPermissions' => false,
            'createAsset' => false,
            'createCollection' => true,
        ]];
    }

    /**
     * @dataProvider getCapabilitiesMatrix
     */
    public function testCapabilitiesFollowAcl(int $mask, array $expectedCapabilities): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);
        $this->grantUserOnObject(self::USER, $collection, $mask);

        $response = $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertEquals($expectedCapabilities, $response->toArray()['capabilities']);
    }

    public function testWorkspaceOwnerHasAllCapabilities(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);

        $response = $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertEquals([
            'edit' => true,
            'delete' => true,
            'editPermissions' => true,
            'createAsset' => true,
            'createCollection' => true,
        ], $response->toArray()['capabilities']);
    }

    public function testAscendants(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A', 'ownerId' => self::USER]);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B', 'parent' => $a, 'ownerId' => self::USER]);
        $c = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'parent' => $b, 'ownerId' => self::USER]);

        $response = $this->request('GET', '/collections/'.$c->getId().'/ascendants', self::USER);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame($c->getId(), $data['id']);
        $this->assertSame('A'.self::SEP.'B'.self::SEP.'C', $data['absoluteName']);
        $this->assertSame($b->getId(), $data['parent']['id']);
        $this->assertSame('B', $data['parent']['name']);
        $this->assertSame($a->getId(), $data['parent']['parent']['id'] ?? $data['parent']['parentId']);

        // The plain GET does not embed the parent
        $response = $this->request('GET', '/collections/'.$c->getId(), self::USER);
        $this->assertArrayNotHasKey('parent', $response->toArray());
    }

    public function testAscendantsDoNotLeakUnreadableParent(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B', 'parent' => $a]);
        $this->grantUserOnObject(self::USER, $b, PermissionInterface::VIEW);

        $response = $this->request('GET', '/collections/'.$b->getId().'/ascendants', self::USER);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertNull($data['parent'] ?? null);
        $this->assertNull($data['parentId'] ?? null);

        $this->request('GET', '/collections/'.$a->getId().'/ascendants', self::USER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPrivacyInfo(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A', 'ownerId' => self::USER]);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B', 'parent' => $a, 'ownerId' => self::USER]);
        $c = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'parent' => $b, 'ownerId' => self::USER]);
        $this->setCollectionPrivacy($a, Privacy::PRIVATE);
        $this->setCollectionPrivacy($b, Privacy::PUBLIC_IN_WORKSPACE);

        $response = $this->request('GET', '/collections/'.$c->getId().'/privacy', self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([
            'privacy' => Privacy::SECRET,
            // The most open privacy of the branch wins
            'computedPrivacy' => Privacy::PRIVATE,
            'canEditAssetPrivacy' => false,
        ], array_intersect_key($response->toArray(), array_flip(['privacy', 'computedPrivacy', 'canEditAssetPrivacy'])));

        // The workspace owner may set the privacy of new assets
        $workspace->setOwnerId(self::OTHER);
        self::getEntityManager()->flush();
        $response = $this->request('GET', '/collections/'.$c->getId().'/privacy', self::OTHER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertTrue($response->toArray()['canEditAssetPrivacy']);
    }

    public function testPrivacyInfoRequiresRead(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret']);

        $this->request('GET', '/collections/'.$collection->getId().'/privacy', self::USER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/collections/'.$collection->getId().'/privacy', self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPrivacyInfoOfUnknownCollection(): void
    {
        $this->markTestIncomplete('BUG: CollectionPrivacyInfoProvider (src/Api/Provider/CollectionPrivacyInfoProvider.php:30) calls DoctrineUtil::findStrictByRepo() without throw404, an unknown ID gives a 500 (InvalidArgumentException) instead of a 404');

        $this->request('GET', '/collections/00000000-0000-4000-8000-000000000000/privacy', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTrashedCollectionIsOnlyReadableByThoseAllowedToDeleteIt(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER, self::OTHER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'ownerId' => self::USER]);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $collection, 'ownerId' => self::USER]);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC_IN_WORKSPACE);
        $collection->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->request('GET', '/collections/'.$collection->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['deleted' => true]);
        // A child of a trashed collection is considered as deleted too
        $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['deleted' => true]);

        $this->request('GET', '/collections/'.$collection->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }
}
