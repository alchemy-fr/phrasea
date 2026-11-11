<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Util\NaturalSortKey;

/**
 * GET /collections (Elasticsearch for admins and for any parent/query filter,
 * the collection_access table for the root collections of regular users).
 */
final class CollectionListTest extends AbstractSearchTestCase
{
    use CollectionTestTrait;

    private const string GROUP = KeycloakClientTestMock::GROUP_UID;

    /**
     * Created in this order, see SORTED_NAMES.
     */
    private const array NAMES = ['Lot 10', 'lot 2', 'Lot 1', 'Été 2024', 'ete 2023', 'Zèbre', 'apple', 'Äpfel', 'B', '  Beta  ', '_misc', '10 ans', '9 vies'];

    /**
     * Natural order, case and accent insensitive ("_" is between the digits and the letters).
     */
    private const array SORTED_NAMES = ['9 vies', '10 ans', '_misc', 'Äpfel', 'apple', 'B', '  Beta  ', 'ete 2023', 'Été 2024', 'Lot 1', 'lot 2', 'Lot 10', 'Zèbre'];

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

        $this->assertSame(['In 1', 'In 2'], $this->listOrderedNames(self::USER, []));

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
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Holidays']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Summer holidays', 'parent' => $root]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Beach holidays', 'parent' => $child]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Work', 'parent' => $child]);
        $this->reindex();

        $this->assertSame(['Beach holidays', 'Holidays', 'Summer holidays'], $this->listNames(self::USER, ['query' => 'holidays']));
        $this->assertSame(['Holidays'], $this->listNames(self::USER, ['query' => 'holidays', 'deep' => 'false']));

        $members = $this->list(self::USER, ['query' => 'summer']);
        $this->assertSame(['Summer holidays'], array_column($members, 'name'));
        $this->assertSame('[hl]Summer[/hl] holidays', $members[0]['nameHighlight']);
    }

    public function testQueryOnNestedCollectionsAppliesPermissions(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Project root']);
        $this->setCollectionPrivacy($root, Privacy::PRIVATE_IN_WORKSPACE);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Project inherited', 'parent' => $root]);
        $secretRoot = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret root']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Project secret', 'parent' => $secretRoot]);
        $shared = $this->createCollection(['workspace' => $workspace, 'name' => 'Project shared', 'parent' => $secretRoot]);
        $this->grantUserOnObject(self::USER, $shared, PermissionInterface::VIEW);
        $this->reindex();

        $this->assertSame(['Project inherited', 'Project root', 'Project shared'], $this->listNames(self::USER, ['query' => 'project']));
        $this->assertSame(['Project inherited', 'Project root', 'Project secret', 'Project shared'], $this->listNames(self::ADMIN, ['query' => 'project']));
        $this->assertSame([], $this->listNames(self::OTHER, ['query' => 'project']));
        $this->assertSame([], $this->listNames(self::ANONYMOUS, ['query' => 'project']));
    }

    /**
     * "query" is a search-as-you-type: the last word matches as a prefix, every word is required.
     */
    public function testQueryMatchesWordPrefixes(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Demo user']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'User guide']);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Academic']);
        $this->reindex();

        $this->assertSame(['Demo user'], $this->listNames(self::USER, ['query' => 'dem']));
        $this->assertSame(['Demo user'], $this->listNames(self::USER, ['query' => 'DEMO us']));
        $this->assertSame(['Demo user', 'User guide'], $this->listNames(self::USER, ['query' => 'use']));
        $this->assertSame(['User guide'], $this->listNames(self::USER, ['query' => 'user gu']));
        $this->assertSame([], $this->listNames(self::USER, ['query' => 'emo']));
        $this->assertSame([], $this->listNames(self::USER, ['query' => 'demo guide']));

        $members = $this->list(self::USER, ['query' => 'dem']);
        $this->assertSame('[hl]Dem[/hl]o user', $members[0]['nameHighlight']);
        $members = $this->list(self::USER, ['query' => 'DEMO us']);
        $this->assertSame('[hl]Demo[/hl] [hl]us[/hl]er', $members[0]['nameHighlight']);
    }

    /**
     * The partial highlight relies on edge n-grams of a limited length: a longer word typed in full
     * is highlighted as a whole word.
     */
    public function testQueryHighlightsLongWordsInFull(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Photographies anciennes']);
        $this->reindex();

        $members = $this->list(self::USER, ['query' => 'photog']);
        $this->assertSame('[hl]Photog[/hl]raphies anciennes', $members[0]['nameHighlight']);
        $members = $this->list(self::USER, ['query' => 'photographies']);
        $this->assertSame('[hl]Photographies[/hl] anciennes', $members[0]['nameHighlight']);
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

    /**
     * "query" also matches any translation of the name (accent-insensitive), and highlights the
     * displayed name, which is the translation in the user locale.
     */
    public function testQuerySearchesTranslatedNames(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Caption',
            'translations' => ['name' => ['fr' => 'Légende', 'de' => 'Bildunterschrift']],
        ]);
        $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Emptied',
            'translations' => ['name' => ['fr' => '']],
        ]);
        $this->createCollection(['workspace' => $workspace, 'name' => 'Work']);
        $this->reindex();

        $this->assertSame(['Caption'], $this->listNames(self::USER, ['query' => 'Légende']));
        $this->assertSame(['Caption'], $this->listNames(self::USER, ['query' => 'legende']));
        $this->assertSame(['Caption'], $this->listNames(self::USER, ['query' => 'bildunterschrift']));
        $this->assertSame(['Emptied'], $this->listNames(self::USER, ['query' => 'emptied']));
        $this->assertSame([], $this->listNames(self::USER, ['query' => 'fr']));
        $this->assertSame([], $this->listNames(self::OTHER, ['query' => 'legende']));

        $members = $this->request('GET', '/collections', self::USER, [
            'query' => ['query' => 'legende'],
            'headers' => ['X-Data-Locale' => 'fr'],
        ])->toArray()['member'];
        $this->assertSame('Légende', $members[0]['displayName']);
        $this->assertSame('[hl]Légende[/hl]', $members[0]['nameHighlight']);

        $members = $this->request('GET', '/collections', self::USER, [
            'query' => ['query' => 'leg'],
            'headers' => ['X-Data-Locale' => 'fr'],
        ])->toArray()['member'];
        $this->assertSame('[hl]Lég[/hl]ende', $members[0]['nameHighlight']);

        // The displayed name does not match: no highlight
        $members = $this->request('GET', '/collections', self::USER, [
            'query' => ['query' => 'legende'],
            'headers' => ['X-Data-Locale' => 'en', 'Accept-Language' => 'en'],
        ])->toArray()['member'];
        $this->assertSame('Caption', $members[0]['displayName']);
        $this->assertArrayNotHasKey('nameHighlight', $members[0]);
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

    public function testAdminRootsAreInNaturalOrder(): void
    {
        $workspace = $this->createTestWorkspace();
        $this->createNamedCollections($workspace);
        $this->reindex();

        $members = $this->list(self::ADMIN, []);
        $this->assertSame(self::SORTED_NAMES, array_column($members, 'name'));
        $this->assertOrderedByDisplayName($members);

        // Same order across pages
        $this->assertSame(array_slice(self::SORTED_NAMES, 5, 5), $this->listOrderedNames(self::ADMIN, ['limit' => 5, 'page' => 2]));
    }

    public function testOwnerRootsAreInNaturalOrder(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        foreach ($this->createNamedCollections($workspace, ['ownerId' => self::USER]) as $collection) {
            // collection_access is not computed on SQLite
            $this->createCollectionAccess($collection, self::USER, Privacy::SECRET);
        }

        $members = $this->list(self::USER, []);
        $this->assertSame(self::SORTED_NAMES, array_column($members, 'name'));
        $this->assertOrderedByDisplayName($members);
    }

    public function testRootsSharedWithUserOrGroupAreInNaturalOrder(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER, self::OTHER]]);
        foreach ($this->createNamedCollections($workspace) as $i => $collection) {
            $this->createCollectionAccess($collection, 0 === $i % 2 ? self::USER : self::GROUP, Privacy::SECRET);
        }
        [$sharedWithUser, $sharedWithGroup] = self::splitSharedNames();

        $this->assertSame($sharedWithUser, $this->listOrderedNames(self::USER, [], ['groups' => []]));
        $this->assertSame($sharedWithGroup, $this->listOrderedNames(self::OTHER, [], ['groups' => [self::GROUP]]));
        $this->assertSame(self::SORTED_NAMES, $this->listOrderedNames(self::USER, [], ['groups' => [self::GROUP]]));
    }

    public function testPublicRootsAreInNaturalOrderForAnonymous(): void
    {
        $workspace = $this->createTestWorkspace(['public' => true]);
        foreach ($this->createNamedCollections($workspace, ['public' => true]) as $collection) {
            $this->createCollectionAccess($collection, null, Privacy::PUBLIC);
        }

        $members = $this->list(self::ANONYMOUS, []);
        $this->assertSame(self::SORTED_NAMES, array_column($members, 'name'));
        $this->assertOrderedByDisplayName($members);
    }

    public function testTrashedRootIsNotListedForUsers(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $kept = $this->createCollection(['workspace' => $workspace, 'name' => 'Kept', 'ownerId' => self::USER]);
        $trashed = $this->createCollection(['workspace' => $workspace, 'name' => 'Trashed', 'ownerId' => self::USER]);
        $shared = $this->createCollection(['workspace' => $workspace, 'name' => 'Shared trashed']);
        foreach ([$kept, $trashed, $shared] as $collection) {
            $this->createCollectionAccess($collection, self::USER, Privacy::SECRET);
        }
        $this->assertSame(['Kept', 'Shared trashed', 'Trashed'], $this->listOrderedNames(self::USER, []));

        foreach ([$trashed->getId(), $shared->getId()] as $id) {
            $this->findCollection($id)->setDeletedAt(new \DateTimeImmutable());
            self::getEntityManager()->flush();
        }

        $this->assertSame(['Kept'], $this->listOrderedNames(self::USER, []));
    }

    public function testTrashedPublicRootIsNotListedForAnonymous(): void
    {
        $workspace = $this->createTestWorkspace(['public' => true]);
        $kept = $this->createCollection(['workspace' => $workspace, 'name' => 'Kept', 'public' => true]);
        $trashed = $this->createCollection(['workspace' => $workspace, 'name' => 'Trashed', 'public' => true]);
        foreach ([$kept, $trashed] as $collection) {
            $this->createCollectionAccess($collection, null, Privacy::PUBLIC);
        }
        $this->assertSame(['Kept', 'Trashed'], $this->listOrderedNames(self::ANONYMOUS, []));

        $this->findCollection($trashed->getId())->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->assertSame(['Kept'], $this->listOrderedNames(self::ANONYMOUS, []));
    }

    /**
     * Same thresholds as the READ of CollectionVoter: below "public", a collection
     * is only listed to authenticated users.
     */
    public function testAnonymousOnlyListsPublicRoots(): void
    {
        $workspace = $this->createTestWorkspace(['public' => true, 'members' => [self::USER]]);
        foreach ([Privacy::PRIVATE_IN_WORKSPACE, Privacy::PUBLIC_IN_WORKSPACE, Privacy::PRIVATE, Privacy::PUBLIC_FOR_USERS, Privacy::PUBLIC] as $privacy) {
            $collection = $this->createCollection(['workspace' => $workspace, 'name' => Privacy::KEYS[$privacy], 'no_flush' => true]);
            $collection->setPrivacy($privacy);
            $this->createCollectionAccess($collection, null, $privacy);
        }

        $this->assertSame(['public'], $this->listNames(self::ANONYMOUS, []));
        $this->assertSame(
            ['private', 'private_in_workspace', 'public', 'public_for_users', 'public_in_workspace'],
            $this->listNames(self::USER, []),
        );
    }

    public function testChildrenAreInNaturalOrder(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER, self::OTHER]]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root', 'ownerId' => self::USER]);
        $this->createNamedCollections($workspace, ['parent' => $root, 'ownerId' => self::USER]);
        $sharedRoot = $this->createCollection(['workspace' => $workspace, 'name' => 'Shared root']);
        foreach ($this->createNamedCollections($workspace, ['parent' => $sharedRoot]) as $i => $collection) {
            if (0 === $i % 2) {
                $this->grantUserOnObject(self::USER, $collection, PermissionInterface::VIEW);
            } else {
                $this->grantGroupOnObject(self::GROUP, $collection, PermissionInterface::VIEW);
            }
        }
        $this->reindex();

        $members = $this->list(self::USER, ['parent' => $root->getId()]);
        $this->assertSame(self::SORTED_NAMES, array_column($members, 'name'));
        $this->assertOrderedByDisplayName($members);
        $this->assertSame(self::SORTED_NAMES, $this->listOrderedNames(self::ADMIN, ['parent' => $root->getId()]));

        [$sharedWithUser, $sharedWithGroup] = self::splitSharedNames();
        $this->assertSame($sharedWithUser, $this->listOrderedNames(self::USER, ['parent' => $sharedRoot->getId()], ['groups' => []]));
        $this->assertSame($sharedWithGroup, $this->listOrderedNames(self::OTHER, ['parent' => $sharedRoot->getId()], ['groups' => [self::GROUP]]));
        $this->assertSame(self::SORTED_NAMES, $this->listOrderedNames(self::USER, ['parent' => $sharedRoot->getId()], ['groups' => [self::GROUP]]));

        // Children embedded in their root
        $members = $this->list(self::ADMIN, []);
        $this->assertSame(['Root', 'Shared root'], array_column($members, 'name'));
        $this->assertSame(self::SORTED_NAMES, array_column($members[0]['children'], 'name'));
        $this->assertOrderedByDisplayName($members[0]['children']);
        $this->assertSame(self::SORTED_NAMES, array_column($members[1]['children'], 'name'));
    }

    /**
     * The order is the one of the displayed name, in the locale of the user.
     */
    public function testTranslatedNamesAreSortedAsDisplayed(): void
    {
        $root = $this->createTranslatedCollections([
            ['Caption', ['fr' => 'Légende']],
            ['Zoo', ['fr' => 'Animaux']],
            ['Beach', null],
        ]);

        foreach ([
            'fr' => ['Animaux', 'Beach', 'Légende'],
            'fr-CA' => ['Animaux', 'Beach', 'Légende'],
            'en' => ['Beach', 'Caption', 'Zoo'],
        ] as $locale => $expected) {
            $headers = ['X-Data-Locale' => $locale];
            foreach ([
                [self::ADMIN, []],
                [self::USER, []],
                [self::USER, ['parent' => $root->getId()]],
            ] as [$userId, $query]) {
                $members = $this->list($userId, $query, $headers);
                $this->assertSame($expected, self::withoutRoot(array_column($members, 'displayName')), $locale);
                $this->assertOrderedByDisplayName($members, $locale);
            }
        }
    }

    public function testTranslatedNamesInTheWorkspaceFallbackLocales(): void
    {
        $root = $this->createTranslatedCollections([
            ['Alpha', ['en' => 'Xylo']],
            ['Mike', ['en_US' => 'Charlie']],
            ['Beta', null],
            ['Zulu', null],
        ], ['de', 'en']);

        // "it" falls back to the workspace locales: "en" of Alpha, the first "en_*" of Mike
        foreach (['it', 'en'] as $locale) {
            $headers = ['X-Data-Locale' => $locale, 'Accept-Language' => $locale];
            foreach ([
                [self::ADMIN, []],
                [self::USER, []],
                [self::USER, ['parent' => $root->getId()]],
            ] as [$userId, $query]) {
                $members = $this->list($userId, $query, $headers);
                $this->assertSame(['Beta', 'Mike', 'Alpha', 'Zulu'], self::withoutRoot(array_column($members, 'name')), $locale);
                $this->assertOrderedByDisplayName($members, $locale);
            }
        }
    }

    /**
     * The Elasticsearch sort script picks the translation of the displayed name (LocaleUtil::getBestLocale()).
     */
    public function testSortScriptPicksTheDisplayedTranslation(): void
    {
        $root = $this->createTranslatedCollections([
            ['n1', ['fr' => 'f1', 'fr_FR' => 'h5', 'en_US' => 'u3', 'en_GB' => 'g4', 'de' => 'd2']],
            ['n2', ['de' => 'd5', 'en_GB' => 'g1', 'en_US' => 'u4', 'fr_FR' => 'h2', 'fr' => 'f3']],
            ['n3', ['en_US' => 'u1', 'fr_FR' => 'h3', 'de' => 'd4']],
            ['n4', ['fr_FR' => 'h4', 'en_GB' => 'g5', 'fr' => 'f2']],
            ['n5', ['en_GB' => 'g2', 'en_US' => 'u5', 'fr' => 'f5', 'de' => 'd1']],
            ['n6', ['fr' => 'f4', 'de' => 'd3', 'en_US' => 'u2']],
            ['n7', ['fr_FR' => 'h1', 'de' => 'd6']],
            ['n8', ['fr' => 'f6', 'fr_CA' => 'c0']],
            ['n0', null],
        ]);

        $orders = [];
        foreach (['', 'fr', 'fr_CA', 'en', 'en, fr-FR', 'en, en-GB', 'de-AT'] as $languages) {
            $headers = '' !== $languages ? ['Accept-Language' => $languages] : [];
            foreach ([
                [self::ADMIN, []],
                [self::USER, []],
                [self::USER, ['parent' => $root->getId()]],
            ] as [$userId, $query]) {
                $members = $this->list($userId, $query, $headers);
                $this->assertCount([] === $query ? 10 : 9, $members);
                $this->assertOrderedByDisplayName($members, $languages);
                $orders[implode(',', array_column($members, 'displayName'))] = true;
            }
        }
        // The languages do pick different translations
        $this->assertGreaterThan(10, count($orders));
    }

    /**
     * A collection created through the API is listed at the place the client inserts it at.
     */
    public function testOrderAfterCreationIsTheOrderAfterReload(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root', 'ownerId' => self::USER]);
        $this->createCollectionAccess($root, self::USER, Privacy::SECRET);
        $this->reindex();

        $headers = ['X-Data-Locale' => 'fr'];
        $children = [];
        $roots = [];
        foreach (['Lot 10', 'lot 2', 'Été 2024', 'ete 2023', 'Zèbre', 'Lot 1', 'Lot 1'] as $i => $name) {
            $translations = 0 === $i ? ['name' => ['fr' => 'Avant tout']] : null;
            $children[] = $this->request('POST', '/collections', self::USER, [
                'headers' => $headers,
                'json' => [
                    'name' => $name,
                    'parent' => '/collections/'.$root->getId(),
                    'translations' => $translations,
                ],
            ])->toArray();
            $this->assertResponseStatusCodeSame(201);
            $roots[] = $this->request('POST', '/collections', self::USER, [
                'headers' => $headers,
                'json' => [
                    'name' => $name,
                    'workspace' => '/workspaces/'.$workspace->getId(),
                    'translations' => $translations,
                ],
            ])->toArray();
            $this->assertResponseStatusCodeSame(201);
        }
        foreach ($roots as $created) {
            // collection_access is not computed on SQLite
            $this->createCollectionAccess($this->findCollection($created['id']), self::USER, Privacy::SECRET);
        }
        self::waitForESIndex('collection');

        $expected = array_column($this->sortLikeTheClient($children), 'id');
        $this->assertSame('Avant tout', $this->sortLikeTheClient($children)[0]['displayName']);
        $this->assertSame($expected, array_column($this->list(self::USER, ['parent' => $root->getId()], $headers), 'id'));

        $members = $this->list(self::USER, [], $headers);
        $this->assertSame(
            array_column($this->sortLikeTheClient([...$roots, ...array_filter($members, static fn (array $m): bool => $m['id'] === $root->getId())]), 'id'),
            array_column($members, 'id'),
        );
    }

    private function reindex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('collection');
    }

    private function list(string $userId, array $query, array $headers = []): array
    {
        $response = $this->request('GET', '/collections', $userId, [
            'query' => $query,
            'headers' => $headers,
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

    /**
     * @return Collection[] the collections of NAMES, in this order
     */
    private function createNamedCollections(Workspace $workspace, array $options = []): array
    {
        return array_map(fn (string $name): Collection => $this->createCollection([
            ...$options,
            'workspace' => $workspace,
            'name' => $name,
        ]), self::NAMES);
    }

    /**
     * @return array{0: string[], 1: string[]} the sorted names of the even (shared with USER) and odd (with GROUP) NAMES
     */
    private static function splitSharedNames(): array
    {
        $odd = array_values(array_filter(self::SORTED_NAMES, static fn (string $name): bool => 1 === array_search($name, self::NAMES, true) % 2));

        return [array_values(array_diff(self::SORTED_NAMES, $odd)), $odd];
    }

    /**
     * Creates the same collections as roots (readable by USER through collection_access) and as children of a "Root".
     *
     * @param array<array{0: string, 1: array<string, string>|null}> $cases name and name translations
     *
     * @return Collection the "Root" collection
     */
    private function createTranslatedCollections(array $cases, ?array $localeFallbacks = null): Collection
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        if (null !== $localeFallbacks) {
            $workspace->setLocaleFallbacks($localeFallbacks);
            self::getEntityManager()->flush();
        }
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root', 'ownerId' => self::USER]);
        $this->createCollectionAccess($root, self::USER, Privacy::SECRET);
        foreach ([null, $root] as $parent) {
            foreach ($cases as [$name, $translations]) {
                $collection = $this->createCollection([
                    'workspace' => $workspace,
                    'name' => $name,
                    'ownerId' => self::USER,
                    'parent' => $parent,
                    'translations' => null !== $translations ? ['name' => $translations] : null,
                ]);
                if (null === $parent) {
                    $this->createCollectionAccess($collection, self::USER, Privacy::SECRET);
                }
            }
        }
        $this->reindex();

        return $root;
    }

    /**
     * @return string[] names, in the order of the response
     */
    private function listOrderedNames(string $userId, array $query, array $options = []): array
    {
        $response = $this->request('GET', '/collections', $userId, [
            ...$options,
            'query' => $query,
        ]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['member'], 'name');
    }

    /**
     * The members in the order of CollectionSorter, CollectionSearch and the client:
     * natural order of the displayed name, then creation date, then ID.
     */
    private function sortLikeTheClient(array $members): array
    {
        $rows = array_map(fn (array $m): array => [
            $m,
            NaturalSortKey::fromString($m['displayName'] ?? $m['name'] ?? ''),
            isset($m['createdAt'])
                ? (new \DateTimeImmutable($m['createdAt']))->getTimestamp()
                : self::getEntityManager()->find(Collection::class, $m['id'])->getCreatedAt()->getTimestamp(),
        ], $members);
        usort($rows, static fn (array $a, array $b): int => NaturalSortKey::compare($a[1], $b[1])
            ?: $a[2] <=> $b[2]
            ?: strcmp($a[0]['id'], $b[0]['id']) <=> 0);

        return array_column($rows, 0);
    }

    private function assertOrderedByDisplayName(array $members, string $message = ''): void
    {
        $this->assertNotEmpty($members);
        $expected = $this->sortLikeTheClient($members);
        $this->assertSame(array_column($expected, 'displayName'), array_column($members, 'displayName'), $message);
        $this->assertSame(array_column($expected, 'id'), array_column($members, 'id'), $message);
    }

    /**
     * @param string[] $names
     *
     * @return string[]
     */
    private static function withoutRoot(array $names): array
    {
        return array_values(array_filter($names, static fn (string $name): bool => 'Root' !== $name));
    }
}
