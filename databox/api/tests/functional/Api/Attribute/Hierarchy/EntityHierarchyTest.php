<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute\Hierarchy;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;

/**
 * Structure of an entity list hierarchy (a forest): parents, depths, filters, import and export.
 */
final class EntityHierarchyTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;
    use GeographyHierarchyTrait;

    private Workspace $workspace;

    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->createGeography();
    }

    private function find(string $id): AttributeEntity
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(AttributeEntity::class, $id);
    }

    public function testEntitiesGetTheirDepth(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Geo', 'isHierarchy' => true]);
        $world = $this->createEntity($list, 'World');
        $atlantis = $this->createEntity($list, 'Atlantis');

        $child = $this->api('POST', '/attribute-entities', self::USER, [
            'list' => '/entity-lists/'.$list->getId(),
            'value' => 'Europe',
            'parent' => '/attribute-entities/'.$world->getId(),
        ])->toArray();
        $this->assertSame(1, $child['hierarchyDepth']);
        $this->assertSame($world->getId(), $child['parentId']);

        // Any entity without parent is at the top level, and takes children
        $this->assertSame(0, $this->find($atlantis->getId())->getHierarchyDepth());
        $this->api('PATCH', '/attribute-entities/'.$child['id'], self::USER, [
            'parent' => '/attribute-entities/'.$atlantis->getId(),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($atlantis->getId(), $this->find($child['id'])->getParentId());

        // Back to the top level
        $this->api('PATCH', '/attribute-entities/'.$child['id'], self::USER, [
            'parent' => null,
            'position' => 0,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->find($child['id'])->getHierarchyDepth());
    }

    public function testCyclesAndForeignParentsAreRefused(): void
    {
        $this->setUpScene();

        $this->api('PATCH', '/attribute-entities/'.$this->e['france']->getId(), self::USER, [
            'parent' => '/attribute-entities/'.$this->e['paris']->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);

        $other = $this->createEntityList(['name' => 'Other', 'isHierarchy' => true]);
        $foreign = $this->createEntity($other, 'Foreign');
        $this->api('PATCH', '/attribute-entities/'.$this->e['france']->getId(), self::USER, [
            'parent' => '/attribute-entities/'.$foreign->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testMovingABranchShiftsTheDepthOfItsSubtree(): void
    {
        $this->setUpScene();

        // Paris directly under France: one level up for its whole branch
        $this->api('PATCH', '/attribute-entities/'.$this->e['paris']->getId(), self::USER, [
            'parent' => '/attribute-entities/'.$this->e['france']->getId(),
            'position' => 0,
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame(3, $this->find($this->e['paris']->getId())->getHierarchyDepth());
        $this->assertSame(5, $this->find($this->e['rvh_paris']->getId())->getHierarchyDepth());
        $this->assertSame(0, $this->find($this->e['paris']->getId())->getPosition());
    }

    public function testEntityWithChildrenCannotBeRemoved(): void
    {
        $this->setUpScene();

        $this->api('DELETE', '/attribute-entities/'.$this->e['paris']->getId(), self::USER);
        $this->assertResponseStatusCodeSame(422);

        $this->api('DELETE', '/attribute-entities/'.$this->e['rome']->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testImpactOfAMove(): void
    {
        $this->setUpScene();
        $this->createGeographyDefinitions();

        $impact = $this->api('GET', '/attribute-entities/'.$this->e['france']->getId().'/hierarchy-impact', self::USER)->toArray();
        $this->assertSame(9, $impact['entityCount']);
        $this->assertSame(0, $impact['attributeCount']);
        $this->assertSame(0, $impact['humanAttributeCount']);
        $this->assertSame(
            ['Continent' => 1, 'Pays' => 2, 'Region' => 3, 'Ville' => 4, 'Arrondissement' => 5, 'Rue' => 6],
            array_column($impact['definitions'], 'depthMin', 'name'),
        );
    }

    public function testListingFiltersAndPath(): void
    {
        $this->setUpScene();
        $this->createGeographyDefinitions();

        $children = $this->api('GET', '/attribute-entities', self::USER, options: ['query' => [
            'parent' => $this->e['europe']->getId(),
        ]])->toArray()['member'];
        $this->assertSame(['France', 'Italie', 'Belgique'], array_column($children, 'value'));
        $this->assertSame(2, $children[0]['childCount']);

        $streets = $this->api('GET', '/attribute-entities', self::USER, options: ['query' => [
            'definition' => $this->defs['rue']->getId(),
        ]])->toArray()['member'];
        $this->assertCount(2, $streets);
        $paths = array_map(fn (array $s): string => implode(' / ', array_column($s['path'], 'value')), $streets);
        $this->assertEqualsCanonicalizing([
            'Monde / Europe / France / Île-de-France / Paris / 16e',
            'Monde / Europe / France / Indre-et-Loire / Tours',
        ], $paths);

        // Placeholders are never proposed
        $levels = $this->api('GET', '/attribute-entities', self::USER, options: ['query' => [
            'definition' => $this->defs['arrondissement']->getId(),
        ]])->toArray()['member'];
        $this->assertSame(['16e'], array_column($levels, 'value'));

        $atlantis = $this->createEntity(self::getEntityManager()->find(EntityList::class, $this->geo->getId()), 'Atlantis');
        $this->assertEqualsCanonicalizing([$this->e['monde']->getId(), $atlantis->getId()], self::ids($this->api('GET', '/attribute-entities', self::USER, options: ['query' => [
            'list' => $this->geo->getId(),
            'depth' => 0,
        ]])));
    }

    public function testImportPlacesExistingEntitiesAndReports(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Geo', 'isHierarchy' => true]);
        foreach (['world', 'eu', 'fr', 'it'] as $key) {
            $entity = $this->createEntity($list, strtoupper($key), ['no_flush' => true]);
            $entity->setExternalId($key);
        }
        self::getEntityManager()->flush();

        $csv = "entity_external_id,parent_external_id,position\nworld,,0\neu,world,0\nfr,eu,1\nit,eu,0\nfr,it,0\nghost,eu,2\n";
        $report = $this->api('POST', '/entity-lists/'.$list->getId().'/hierarchy-import', self::USER, ['data' => $csv, 'dryRun' => true])->toArray();
        $this->assertSame(4, $report['applied']);
        $this->assertSame([6, 7], array_column($report['rejected'], 'line'));

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->getRepository(AttributeEntity::class)->findOneBy(['externalId' => 'fr'])->getParent());

        $this->api('POST', '/entity-lists/'.$list->getId().'/hierarchy-import', self::USER, ['data' => $csv]);
        $this->assertResponseIsSuccessful();
        $em->clear();
        $fr = $em->getRepository(AttributeEntity::class)->findOneBy(['externalId' => 'fr']);
        $this->assertSame(2, $fr->getHierarchyDepth());
        $this->assertSame('EU', $fr->getParent()->getValue());

        $export = $this->api('POST', '/entity-lists/'.$list->getId().'/hierarchy-export', self::USER, [])->getContent();
        $this->assertStringContainsString("world,,0,WORLD,\neu,world,0,EU,\nit,eu,0,IT,\nfr,eu,1,FR,", $export);
    }
}
