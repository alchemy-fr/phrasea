<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;

/**
 * /attribute-entities?query=…: the Elasticsearch search-as-you-type listing.
 *
 * The parameters declared on the collection (list, value, order[value], workspace) apply
 * to this search exactly as they apply to the plain ORM listing.
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
}
