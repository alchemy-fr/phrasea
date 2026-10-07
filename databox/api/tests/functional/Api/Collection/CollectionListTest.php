<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * GET /collections (Elasticsearch for admins and for any parent/query filter,
 * the collection_access table for the root collections of regular users).
 */
final class CollectionListTest extends AbstractSearchTestCase
{
    use CollectionTestTrait;

    public function testAdminListsRootCollectionsWithTheirChildren(): void
    {
        $workspace = $this->createTestWorkspace();
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'b root']);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A root']);
        foreach (['A2', 'A1', 'A3'] as $name) {
            $child = $this->createCollection(['workspace' => $workspace, 'name' => $name, 'parent' => $a]);
        }
        $this->createCollection(['workspace' => $workspace, 'name' => 'Deep', 'parent' => $child]);
        self::populateSearchIndices();

        $response = $this->request('GET', '/collections', self::ADMIN);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
        // Sorted by name, case insensitive
        $this->assertSame(['A root', 'b root'], array_column($data['member'], 'name'));

        [$first, $second] = $data['member'];
        $this->assertSame(['A1', 'A2', 'A3'], array_column($first['children'], 'name'));
        $this->assertSame([], $second['children']);
        // Children are embedded on one level only
        foreach ($first['children'] as $child) {
            $this->assertArrayNotHasKey('children', $child);
        }
        // List items carry their capabilities, but not the read-only fields
        $this->assertArrayHasKey('capabilities', $first);
        $this->assertArrayNotHasKey('owner', $first);
        $this->assertArrayNotHasKey('absolutePath', $first);
        $this->assertSame($b->getId(), $second['id']);

        $response = $this->request('GET', '/collections?childrenLimit=2', self::ADMIN);
        $this->assertSame(['A1', 'A2'], array_column($response->toArray()['member'][0]['children'], 'name'));
    }

    public function testChildCreatedThroughApiIsEmbeddedInItsParent(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $this->reindex();

        $this->assertSame([[]], array_column($this->list(self::ADMIN, []), 'children'));

        $this->request('POST', '/collections', self::USER, [
            'json' => [
                'name' => 'New child',
                'parent' => '/collections/'.$root->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        self::waitForESIndex('collection');

        $members = $this->list(self::ADMIN, []);
        $this->assertSame(['Root'], array_column($members, 'name'));
        $this->assertSame(['New child'], array_column($members[0]['children'], 'name'));
    }

    public function testMovedCollectionIsListedUnderItsNewParent(): void
    {
        [$source, $moved, $target] = $this->createMoveFixtures();

        $this->request('PUT', '/collections/'.$moved->getId().'/move/'.$target->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::waitForESIndex('collection');

        $this->assertSame(['Moved'], $this->listNames(self::USER, ['parent' => $target->getId()]));
        $this->assertSame([], $this->listNames(self::USER, ['parent' => $source->getId()]));

        $this->request('PUT', '/collections/'.$moved->getId().'/move/root', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::waitForESIndex('collection');

        $this->assertSame([], $this->listNames(self::USER, ['parent' => $target->getId()]));
        $this->assertSame(['Moved', 'Source', 'Target'], $this->listNames(self::ADMIN, []));
    }

    public function testMovedCollectionIsEmbeddedInItsNewParent(): void
    {
        $this->markTestIncomplete('BUG: moving a collection does not reindex its new parent (only the moved branch is, see CollectionListener::postUpdate, src/Doctrine/Listener/CollectionListener.php:67): the "hasChildren" flag of a former leaf stays false in ES, so its children are not embedded in the list (no expand arrow in the tree) until a full reindex');

        [, $moved, $target] = $this->createMoveFixtures();

        $this->request('PUT', '/collections/'.$moved->getId().'/move/'.$target->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::waitForESIndex('collection');

        $members = $this->list(self::ADMIN, []);
        $this->assertSame(['Source', 'Target'], array_column($members, 'name'));
        $this->assertSame(['Moved'], array_column($members[1]['children'], 'name'));
    }

    /**
     * @return Collection[] Source, Moved (child of Source) and Target (a leaf)
     */
    private function createMoveFixtures(): array
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $source = $this->createCollection(['workspace' => $workspace, 'name' => 'Source']);
        $moved = $this->createCollection(['workspace' => $workspace, 'name' => 'Moved', 'parent' => $source]);
        $target = $this->createCollection(['workspace' => $workspace, 'name' => 'Target']);
        self::populateSearchIndices();

        return [$source, $moved, $target];
    }

    public function testPagination(): void
    {
        $workspace = $this->createTestWorkspace();
        foreach (['C', 'A', 'B'] as $name) {
            $this->createCollection(['workspace' => $workspace, 'name' => $name, 'no_flush' => true]);
        }
        self::getEntityManager()->flush();
        $this->reindex();

        $response = $this->request('GET', '/collections?limit=2', self::ADMIN);
        $data = $response->toArray();
        $this->assertSame(3, $data['totalItems']);
        $this->assertSame(['A', 'B'], array_column($data['member'], 'name'));
        $this->assertArrayHasKey('next', $data['view']);

        $response = $this->request('GET', '/collections?limit=2&page=2', self::ADMIN);
        $this->assertSame(['C'], array_column($response->toArray()['member'], 'name'));
    }

    public function testWorkspacesFilter(): void
    {
        $ws1 = $this->createTestWorkspace();
        $ws2 = $this->createTestWorkspace();
        $this->createCollection(['workspace' => $ws1, 'name' => 'In 1']);
        $this->createCollection(['workspace' => $ws2, 'name' => 'In 2']);
        $this->reindex();

        $response = $this->request('GET', '/collections', self::ADMIN, [
            'query' => ['workspaces' => [$ws2->getId()]],
        ]);
        $this->assertSame(['In 2'], array_column($response->toArray()['member'], 'name'));

        // A plain string is accepted as well
        $response = $this->request('GET', '/collections?workspaces='.$ws1->getId(), self::ADMIN);
        $this->assertSame(['In 1'], array_column($response->toArray()['member'], 'name'));
    }

    public function testWorkspacesFilterOnUserRootCollections(): void
    {
        $ws1 = $this->createTestWorkspace(['members' => [self::USER]]);
        $ws2 = $this->createTestWorkspace(['members' => [self::USER]]);
        $c1 = $this->createCollection(['workspace' => $ws1, 'name' => 'In 1', 'ownerId' => self::USER]);
        $c2 = $this->createCollection(['workspace' => $ws2, 'name' => 'In 2', 'ownerId' => self::USER]);
        // collection_access is not computed on SQLite
        $this->createCollectionAccess($c1, self::USER, Privacy::SECRET);
        $this->createCollectionAccess($c2, self::USER, Privacy::SECRET);

        $response = $this->request('GET', '/collections', self::USER);
        $names = array_column($response->toArray()['member'], 'name');
        sort($names);
        $this->assertSame(['In 1', 'In 2'], $names);

        $response = $this->request('GET', '/collections', self::USER, [
            'query' => ['workspaces' => [$ws2->getId()]],
        ]);
        $this->assertSame(['In 2'], array_column($response->toArray()['member'], 'name'));

        // Workspaces the user is not allowed in are ignored
        $ws3 = $this->createTestWorkspace();
        $c3 = $this->createCollection(['workspace' => $ws3, 'name' => 'In 3', 'ownerId' => self::USER]);
        $this->createCollectionAccess($c3, self::USER, Privacy::SECRET);
        $response = $this->request('GET', '/collections', self::USER, [
            'query' => ['workspaces' => [$ws3->getId()]],
        ]);
        $this->assertSame([], $response->toArray()['member']);
    }

    public function testScalarWorkspacesFilterOnUserRootCollections(): void
    {
        $ws1 = $this->createTestWorkspace(['members' => [self::USER]]);
        $ws2 = $this->createTestWorkspace(['members' => [self::USER]]);
        $c1 = $this->createCollection(['workspace' => $ws1, 'name' => 'In 1', 'ownerId' => self::USER]);
        $c2 = $this->createCollection(['workspace' => $ws2, 'name' => 'In 2', 'ownerId' => self::USER]);
        $this->createCollectionAccess($c1, self::USER, Privacy::SECRET);
        $this->createCollectionAccess($c2, self::USER, Privacy::SECRET);

        // A plain string is accepted on the root listing (no Elasticsearch) as well
        $this->assertSame(['In 2'], $this->listNames(self::USER, ['workspaces' => $ws2->getId()]));
    }

    public function testDeepListsTheDescendantsOfTheParent(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Grand child', 'parent' => $child]);
        $this->reindex();

        $this->assertSame(['Child'], $this->listNames(self::USER, ['parent' => $root->getId(), 'deep' => 'false']));
        $this->assertSame(['Child', 'Grand child'], $this->listNames(self::USER, ['parent' => $root->getId(), 'deep' => 'true']));
        $this->assertSame(['Child'], $this->listNames(self::USER, ['parent' => $root->getId(), 'query' => 'child', 'deep' => 'false']));

        $this->request('GET', '/collections', self::USER, ['query' => ['parent' => $root->getId(), 'deep' => 'maybe']]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testParentFilterListsDirectChildrenOnly(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Grand child', 'parent' => $child]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Other root']);
        self::populateSearchIndices();

        $response = $this->request('GET', '/collections', self::USER, [
            'query' => ['parent' => $root->getId()],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame(['Child'], array_column($data['member'], 'name'));
        $this->assertSame(['Grand child'], array_column($data['member'][0]['children'], 'name'));

        // "parents" with a single value is equivalent
        $response = $this->request('GET', '/collections', self::USER, [
            'query' => ['parents' => [$child->getId()]],
        ]);
        $this->assertSame(['Grand child'], array_column($response->toArray()['member'], 'name'));
    }

    public function testParentFilterAppliesPermissions(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        // The parent itself needs not be readable
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Secret child', 'parent' => $root]);
        $mine = $this->createCollection(['workspace' => $workspace, 'name' => 'My child', 'parent' => $root, 'ownerId' => self::USER]);
        $shared = $this->createCollection(['workspace' => $workspace, 'name' => 'Shared child', 'parent' => $root]);
        $open = $this->createCollection(['workspace' => $workspace, 'name' => 'Open child', 'parent' => $root]);
        $this->setCollectionPrivacy($open, Privacy::PUBLIC_IN_WORKSPACE);
        $this->grantUserOnObject(self::USER, $shared, PermissionInterface::VIEW);
        $this->reindex();

        $this->assertSame(['My child', 'Open child', 'Shared child'], $this->listNames(self::USER, ['parent' => $root->getId()]));
        $this->assertSame(['My child', 'Open child', 'Secret child', 'Shared child'], $this->listNames(self::ADMIN, ['parent' => $root->getId()]));
        // Not a member of the workspace
        $this->assertSame([], $this->listNames(self::OTHER, ['parent' => $root->getId()]));
        $this->assertSame([], $this->listNames(self::ANONYMOUS, ['parent' => $root->getId()]));
    }

    public function testChildrenInheritTheVisibilityOfTheirAncestors(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $this->setCollectionPrivacy($root, Privacy::PRIVATE_IN_WORKSPACE);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret child', 'parent' => $root]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Secret grand child', 'parent' => $child]);
        $shared = $this->createCollection(['workspace' => $workspace, 'name' => 'Shared root']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Child of shared', 'parent' => $shared]);
        $this->grantUserOnObject(self::USER, $shared, PermissionInterface::VIEW);
        $this->reindex();

        $this->assertSame(['Secret child'], $this->listNames(self::USER, ['parent' => $root->getId()]));
        $this->assertSame(['Secret grand child'], $this->listNames(self::USER, ['parent' => $child->getId()]));
        $this->assertSame(['Child of shared'], $this->listNames(self::USER, ['parent' => $shared->getId()]));
        $this->assertSame([], $this->listNames(self::OTHER, ['parent' => $shared->getId()]));
    }

    public function testUnknownParent(): void
    {
        $this->markTestIncomplete('BUG: CollectionSearch::applyFilters() (src/Elasticsearch/CollectionSearch.php:118) drops unknown parent IDs: the empty bool query matches everything and no pathDepth filter is applied, so "?parent=<unknown>" lists every collection of every depth');

        $workspace = $this->createTestWorkspace();
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root]);
        $this->reindex();

        $this->assertSame([], $this->listNames(self::ADMIN, ['parent' => '00000000-0000-4000-8000-000000000000']));
    }

    public function testQuerySearchesDescendantsOfParent(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Holidays']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Summer holidays', 'parent' => $root]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Beach holidays', 'parent' => $child]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Work', 'parent' => $child]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Other holidays']);
        $this->reindex();

        $response = $this->request('GET', '/collections', self::USER, [
            'query' => ['parent' => $root->getId(), 'query' => 'holidays'],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $members = $response->toArray()['member'];
        $this->assertSame(['Beach holidays', 'Summer holidays'], array_column($members, 'name'));
        $this->assertStringContainsString('[hl]', $members[0]['nameHighlight']);
    }

    public function testQuerySearchesNestedCollections(): void
    {
        $this->markTestIncomplete('BUG: without parent, CollectionSearch::applyFilters() (src/Elasticsearch/CollectionSearch.php:134) always restricts to pathDepth 0, although a query is meant to be "deep": searching collections by name (CollectionsPanel, AQL suggestions) never finds sub-collections');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Holidays']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Summer holidays', 'parent' => $root]);
        $this->reindex();

        $this->assertSame(['Holidays', 'Summer holidays'], $this->listNames(self::USER, ['query' => 'holidays']));
    }

    public function testQuerySearchesRootCollectionsByName(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Holidays 2024']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Work']);
        $this->reindex();

        $this->assertSame(['Holidays 2024'], $this->listNames(self::USER, ['query' => 'holidays']));
        $this->assertSame([], $this->listNames(self::OTHER, ['query' => 'holidays']));
    }

    public function testTrashedCollectionsAreListedInTrashOnly(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $trashed = $this->createCollection(['workspace' => $workspace, 'name' => 'Trashed']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Kept']);
        $this->reindex();

        $this->request('POST', '/collections/delete-multiple', self::USER, [
            'json' => ['ids' => [$trashed->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::waitForESIndex('collection');

        $this->assertSame(['Kept'], $this->listNames(self::ADMIN, []));
        $this->assertSame(['Trashed'], $this->listNames(self::ADMIN, ['query' => 'in:trash']));
        $this->assertSame(['Kept', 'Trashed'], $this->listNames(self::ADMIN, ['query' => 'in:all']));

        $this->request('POST', '/collections/restore-multiple', self::USER, [
            'json' => ['ids' => [$trashed->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::waitForESIndex('collection');

        $this->assertSame([], $this->listNames(self::ADMIN, ['query' => 'in:trash']));
        $this->assertSame(['Kept', 'Trashed'], $this->listNames(self::ADMIN, []));
    }

    private function reindex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('collection');
    }

    private function list(string $userId, array $query): array
    {
        $response = $this->request('GET', '/collections', $userId, [
            'query' => $query,
        ]);
        $this->assertResponseStatusCodeSame(200);

        return $response->toArray()['member'];
    }

    /**
     * @return string[] sorted names
     */
    private function listNames(string $userId, array $query): array
    {
        $response = $this->request('GET', '/collections', $userId, [
            'query' => $query,
        ]);
        $this->assertResponseStatusCodeSame(200);

        $names = array_column($response->toArray()['member'], 'name');
        sort($names);

        return $names;
    }
}
