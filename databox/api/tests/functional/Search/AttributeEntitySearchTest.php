<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;

/**
 * /attribute-entities?query=…: the Elasticsearch search-as-you-type listing.
 *
 * The parameters declared on the collection (list, value, order[value], workspace) apply
 * to this search exactly as they apply to the plain ORM listing, and so does the visibility
 * of the pending and rejected values.
 */
final class AttributeEntitySearchTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;
    private EntityList $colors;
    private EntityList $shapes;

    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->colors = $this->createEntityList(['name' => 'Colors']);
        $this->shapes = $this->createEntityList(['name' => 'Shapes']);
        $this->createEntity($this->colors, 'Dark blue');
        $this->createEntity($this->colors, 'Red');
        $this->createEntity($this->colors, 'Light blue');
        $this->createEntity($this->shapes, 'Blue square');
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('attribute_entity');
    }

    private function searchValues(?string $userId, array $query): array
    {
        $response = $this->api('GET', '/attribute-entities', $userId, options: ['query' => $query]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['member'], 'value');
    }

    public function testQueryAppliesTheDeclaredFilters(): void
    {
        $this->setUpScene();

        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue', 'Blue square'], $this->searchValues(self::OTHER, ['query' => 'blu']));
        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'list' => $this->colors->getId(),
        ]));
        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'list' => ['/entity-lists/'.$this->colors->getId()],
        ]));
        $this->assertSame(['Light blue', 'Dark blue', 'Blue square'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'order' => ['value' => 'desc'],
        ]));
        $this->assertSame(['Dark blue'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'value' => 'DARK',
        ]));
        $this->assertSame(['Blue square'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'order' => ['value' => 'asc'],
            'limit' => 1,
        ]));
        $this->assertSame(['Dark blue'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'order' => ['value' => 'asc'],
            'limit' => 1,
            'page' => 2,
        ]));
    }

    public function testQueryLinksTheNextPage(): void
    {
        $this->setUpScene();

        $first = $this->api('GET', '/attribute-entities', self::OTHER, options: ['query' => [
            'query' => 'blu',
            'order' => ['value' => 'asc'],
            'limit' => 2,
        ]])->toArray();
        $this->assertSame(['Blue square', 'Dark blue'], array_column($first['member'], 'value'));
        $this->assertSame(3, $first['totalItems']);
        $this->assertStringContainsString('page=2', $first['view']['next']);
        $this->assertStringContainsString('limit=2', $first['view']['next']);

        $last = $this->api('GET', $first['view']['next'], self::OTHER)->toArray();
        $this->assertSame(['Light blue'], array_column($last['member'], 'value'));
        $this->assertArrayNotHasKey('next', $last['view']);
    }

    public function testQueryIgnoresTheOrmOnlySorts(): void
    {
        $this->setUpScene();

        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue', 'Blue square'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'order' => ['createdAt' => 'desc'],
        ]));
        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue', 'Blue square'], $this->searchValues(self::OTHER, [
            'query' => 'blu',
            'order' => ['position' => 'asc'],
        ]));
    }

    public function testQueryIsRestrictedToReadableWorkspaces(): void
    {
        $this->setUpScene();
        $foreignWorkspace = $this->createOtherWorkspace();
        $foreignList = $this->createEntityList(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);
        $this->createEntity($foreignList, 'Blue foreign');
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('attribute_entity');

        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue', 'Blue square'], $this->searchValues(self::OTHER, ['query' => 'blu']));
        $this->assertEqualsCanonicalizing(['Dark blue', 'Light blue', 'Blue square', 'Blue foreign'], $this->searchValues(self::ADMIN, ['query' => 'blu']));
        $this->assertSame(['Blue foreign'], $this->searchValues(self::ADMIN, [
            'query' => 'blu',
            'workspace' => '/workspaces/'.$foreignWorkspace->getId(),
        ]));

        $this->api('GET', '/attribute-entities', self::OTHER, options: ['query' => [
            'query' => 'blu',
            'workspace' => '/workspaces/'.$foreignWorkspace->getId(),
        ]]);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * USER owns the workspace (hence edits its attributes and its lists), OTHER is a mere member.
     */
    private function setUpStatusScene(): void
    {
        $this->setUpScene();
        $this->createEntity($this->colors, 'Teal approved');
        $this->createEntity($this->colors, 'Teal pending by OTHER', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);
        $this->createEntity($this->colors, 'Teal rejected by OTHER', ['status' => AttributeEntity::STATUS_REJECTED, 'creatorId' => self::OTHER]);
        $this->createEntity($this->colors, 'Teal pending by someone', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);
        $this->createEntity($this->colors, 'Teal rejected by someone', ['status' => AttributeEntity::STATUS_REJECTED, 'creatorId' => 'someone']);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('attribute_entity');
    }

    private function getIndexedDocument(AttributeEntity $entity): array
    {
        return self::$documentIndices['attribute_entity']->getDocument($entity->getId())->getData();
    }

    public function testTheDocumentCarriesTheStatusAndTheCreator(): void
    {
        $this->setUpScene();
        $entity = $this->createEntity($this->colors, 'Teal', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('attribute_entity');

        $document = $this->getIndexedDocument($entity);
        $this->assertEquals(AttributeEntity::STATUS_PENDING, $document['status']);
        $this->assertSame(self::OTHER, $document['creatorId']);
    }

    public function testQueryOnlyShowsApprovedValuesAndOwnProposals(): void
    {
        $this->setUpStatusScene();

        $this->assertEqualsCanonicalizing(
            ['Teal approved', 'Teal pending by OTHER', 'Teal rejected by OTHER'],
            $this->searchValues(self::OTHER, ['query' => 'tea']),
        );
        // ...even on the list
        $this->assertEqualsCanonicalizing(
            ['Teal approved', 'Teal pending by OTHER', 'Teal rejected by OTHER'],
            $this->searchValues(self::OTHER, ['query' => 'tea', 'list' => $this->colors->getId()]),
        );

        // Whoever may edit the attributes of the workspace also sees its pending values, not the rejected ones of others
        $this->assertEqualsCanonicalizing(
            ['Teal approved', 'Teal pending by OTHER', 'Teal pending by someone'],
            $this->searchValues(self::USER, ['query' => 'tea']),
        );

        // A list editor filtering on the list sees every status
        $this->assertEqualsCanonicalizing(
            ['Teal approved', 'Teal pending by OTHER', 'Teal rejected by OTHER', 'Teal pending by someone', 'Teal rejected by someone'],
            $this->searchValues(self::USER, ['query' => 'tea', 'list' => '/entity-lists/'.$this->colors->getId()]),
        );
    }

    public function testTheDocumentFollowsTheStatusChanges(): void
    {
        $this->setUpScene();
        $proposal = $this->createEntity($this->colors, 'Teal proposal', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('attribute_entity');
        $this->assertSame([], $this->searchValues(self::OTHER, ['query' => 'tea']));
        $this->assertSame(['Teal proposal'], $this->searchValues(self::USER, ['query' => 'tea']));

        $this->api('PATCH', '/attribute-entities/'.$proposal->getId(), self::USER, ['status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(200);
        self::waitForESIndex('attribute_entity');
        $this->assertEquals(AttributeEntity::STATUS_APPROVED, $this->getIndexedDocument($proposal)['status']);
        $this->assertSame(['Teal proposal'], $this->searchValues(self::OTHER, ['query' => 'tea']));

        $this->api('PATCH', '/attribute-entities/'.$proposal->getId(), self::USER, ['status' => AttributeEntity::STATUS_REJECTED]);
        $this->assertResponseStatusCodeSame(200);
        self::waitForESIndex('attribute_entity');
        $this->assertEquals(AttributeEntity::STATUS_REJECTED, $this->getIndexedDocument($proposal)['status']);
        $this->assertSame([], $this->searchValues(self::OTHER, ['query' => 'tea']));
        $this->assertSame([], $this->searchValues(self::USER, ['query' => 'tea']));
    }
}
