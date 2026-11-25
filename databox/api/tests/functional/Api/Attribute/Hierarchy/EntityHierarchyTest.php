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

        // The ancestors, from the parent up, placeholders included: the client opens the tree with them
        $tours = array_values(array_filter($streets, fn (array $s): bool => $s['id'] === $this->e['rvh_tours']->getId()))[0];
        $this->assertSame(array_map(fn (string $k): string => $this->e[$k]->getId(), [
            'tours_none', 'tours', 'indre', 'france', 'europe', 'monde',
        ]), $tours['ancestorIds']);

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

        $csv = "entity_external_id,parent_external_id\nworld,\neu,world\nit,eu\nfr,eu\nfr,it\nghost,eu\n";
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
        // The order of the file among the siblings
        $this->assertSame(1, $fr->getPosition());
        $this->assertSame(0, $em->getRepository(AttributeEntity::class)->findOneBy(['externalId' => 'it'])->getPosition());

        $export = $this->exportHierarchy($list);
        $this->assertSame([
            ['world', '', 'WORLD'],
            ['eu', 'world', 'EU'],
            ['it', 'eu', 'IT'],
            ['fr', 'eu', 'FR'],
        ], array_map(fn (array $r): array => [$r['entity_external_id'], $r['parent_external_id'], $r['value']], $export));
        // The columns of the values CSV
        $this->assertArrayNotHasKey('id', $export[3]);
        $this->assertArrayNotHasKey('external_id', $export[3]);
        $this->assertSame('0', $export[3]['status']);
        $this->assertSame('', $export[3]['placeholder']);
    }

    public function testImportUpdatesTheValueColumns(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->workspace->setEnabledLocales(['en', 'fr', 'de']);
        $list = $this->createEntityList(['name' => 'Geo', 'isHierarchy' => true]);
        foreach (['eu', 'fr', 'it'] as $key) {
            $entity = $this->createEntity($list, strtoupper($key), ['no_flush' => true]);
            $entity->setExternalId($key);
            $entity->setTranslations(['en' => strtoupper($key).' (en)', 'de' => strtoupper($key).' (de)']);
        }
        self::getEntityManager()->flush();

        $csv = "entity_external_id,parent_external_id,value,emoji,color,status,translation_en,translation_fr\n"
            ."eu,,Europe,🇪🇺,#0000ff,0,,Europe (fr)\n"
            ."fr,eu,France,,,1,France (en),\n"
            ."it,eu,,,notacolor,0,,\n"
            ."ghost,eu,Ghost,,,9,,\n";
        $report = $this->api('POST', '/entity-lists/'.$list->getId().'/hierarchy-import', self::USER, ['data' => $csv])->toArray();
        $this->assertSame(2, $report['applied']);
        $this->assertSame([4, 5], array_column($report['rejected'], 'line'));
        $this->assertStringContainsString('color', $report['rejected'][0]['reason']);

        $em = self::getEntityManager();
        $em->clear();
        $repo = $em->getRepository(AttributeEntity::class);
        $eu = $repo->findOneBy(['externalId' => 'eu']);
        $this->assertSame('Europe', $eu->getValue());
        $this->assertSame('🇪🇺', $eu->getEmoji());
        $this->assertSame('#0000ff', $eu->getColor());
        // An empty translation removes it, the locales without column are kept
        $this->assertSame(['de' => 'EU (de)', 'fr' => 'Europe (fr)'], $eu->getTranslations());
        $fr = $repo->findOneBy(['externalId' => 'fr']);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $fr->getStatus());
        $this->assertSame('France (en)', $fr->getTranslations()['en']);
        $this->assertSame($eu->getId(), $fr->getParentId());
        // The rejected row changes nothing
        $it = $repo->findOneBy(['externalId' => 'it']);
        $this->assertSame('IT', $it->getValue());
        $this->assertNull($it->getParent());

        $row = $this->exportHierarchy($list)[0];
        $this->assertSame(['Europe', '🇪🇺', '#0000ff', '', 'Europe (fr)'], [$row['value'], $row['emoji'], $row['color'], $row['translation_en'], $row['translation_fr']]);
    }

    public function testImportCreatesTheUnknownEntities(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Geo', 'isHierarchy' => true]);
        $this->createEntity($list, 'EU', ['no_flush' => true])->setExternalId('eu');
        self::getEntityManager()->flush();

        $csv = "entity_external_id,parent_external_id,value,placeholder\n"
            ."world,,World,\n"
            ."eu,world,,\n"
            // Before its parent
            ."tx,us,Texas,\n"
            ."us,world,USA,\n"
            ."lvl,tx,,1\n"
            ."kid,ghost,Kid,\n"
            ."kid2,kid,Kid 2,\n"
            ."novalue,,,\n";
        $url = '/entity-lists/'.$list->getId().'/hierarchy-import';
        $report = $this->api('POST', $url, self::USER, ['data' => $csv, 'dryRun' => true])->toArray();
        $this->assertSame(5, $report['applied']);
        $this->assertSame(4, $report['created']);
        $this->assertSame([7, 8, 9], array_column($report['rejected'], 'line'));
        $this->assertSame('Parent "kid" not created', $report['rejected'][1]['reason']);

        $em = self::getEntityManager();
        $em->clear();
        $repo = $em->getRepository(AttributeEntity::class);
        $this->assertNull($repo->findOneBy(['externalId' => 'world']));

        $this->assertSame(4, $this->api('POST', $url, self::USER, ['data' => $csv])->toArray()['created']);
        $em->clear();
        $get = fn (string $key): AttributeEntity => $repo->findOneBy(['list' => $list->getId(), 'externalId' => $key]);
        $this->assertSame('World', $get('world')->getValue());
        $this->assertNull($get('world')->getParent());
        // Placed under a new parent, its value kept
        $this->assertSame('EU', $get('eu')->getValue());
        $this->assertSame([$get('world')->getId(), 1, 0], [$get('eu')->getParentId(), $get('eu')->getHierarchyDepth(), $get('eu')->getPosition()]);
        $this->assertSame([$get('world')->getId(), 1, 1], [$get('us')->getParentId(), $get('us')->getHierarchyDepth(), $get('us')->getPosition()]);
        $this->assertSame([$get('us')->getId(), 2], [$get('tx')->getParentId(), $get('tx')->getHierarchyDepth()]);
        $lvl = $get('lvl');
        $this->assertTrue($lvl->isPlaceholder());
        $this->assertSame(3, $lvl->getHierarchyDepth());
        foreach (['kid', 'kid2', 'novalue'] as $key) {
            $this->assertNull($repo->findOneBy(['externalId' => $key]));
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function exportHierarchy(EntityList $list): array
    {
        $csv = $this->api('POST', '/entity-lists/'.$list->getId().'/hierarchy-export', self::USER, [])->getContent();
        $lines = array_map(fn (string $l): array => str_getcsv($l, escape: ''), explode("\n", trim($csv)));
        $header = array_shift($lines);

        return array_map(fn (array $l): array => array_combine($header, $l), $lines);
    }
}
