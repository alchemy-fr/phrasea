<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute\Hierarchy;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;

/**
 * The "isHierarchy" flag of an entity list: a flat list takes no hierarchy, and turning the flag off flattens it.
 */
final class EntityListHierarchyFlagTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;
    use GeographyHierarchyTrait;

    public function testTheFlagIsOffByDefaultAndWritable(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Colors']);

        $this->assertFalse($this->api('GET', '/entity-lists/'.$list->getId(), self::USER)->toArray()['isHierarchy']);

        $this->api('PATCH', '/entity-lists/'.$list->getId(), self::USER, ['isHierarchy' => true]);
        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->api('GET', '/entity-lists/'.$list->getId(), self::USER)->toArray()['isHierarchy']);
    }

    public function testAFlatListTakesNoHierarchy(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Colors']);
        $red = $this->createEntity($list, 'Red');
        $iri = '/entity-lists/'.$list->getId();
        $definition = $this->createAttributeDefinition([
            'name' => 'Color',
            'type' => EntityAttributeType::NAME,
            'list' => $list,
        ]);

        $this->api('POST', '/attribute-entities', self::USER, [
            'list' => $iri,
            'value' => 'Dark red',
            'parent' => '/attribute-entities/'.$red->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->api('POST', '/attribute-entities', self::USER, ['list' => $iri, 'value' => '—', 'placeholder' => true]);
        $this->assertResponseStatusCodeSame(422);

        // Indented thesaurus: rejected; flat one: imported
        $this->api('POST', $iri.'/import', self::USER, ['format' => 'thesaurus', 'data' => "Red\n    Dark red"]);
        $this->assertResponseStatusCodeSame(400);
        $this->api('POST', $iri.'/import', self::USER, ['format' => 'thesaurus', 'data' => "Blue\nGreen"]);
        $this->assertResponseStatusCodeSame(202);

        $this->api('POST', $iri.'/hierarchy-import', self::USER, ['data' => "entity_external_id,parent_external_id,position\n"]);
        $this->assertResponseStatusCodeSame(400);

        $this->api('PATCH', '/attribute-definitions/'.$definition->getId(), self::USER, ['entityHierarchyDepthMin' => 0]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testTurningTheFlagOffFlattensTheHierarchy(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->createGeography();
        $this->createGeographyDefinitions();
        $asset = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::USER]);
        $this->api('POST', '/assets/'.$asset->getId().'/attributes', self::USER, ['actions' => [[
            'action' => 'set',
            'definitionId' => $this->defs['ville']->getId(),
            'origin' => 'human',
            'value' => $this->e['paris']->getId(),
        ]]]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(4, $this->attributesOf($asset), 'Ville, filled up to the continent');

        $this->api('PATCH', '/entity-lists/'.$this->geo->getId(), self::USER, ['isHierarchy' => false]);
        $this->assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $em->clear();
        /** @var AttributeEntity[] $entities */
        $entities = $em->getRepository(AttributeEntity::class)->findBy(['list' => $this->geo->getId()]);
        $this->assertCount(14, $entities, 'The placeholder is removed');
        foreach ($entities as $entity) {
            $this->assertNull($entity->getParentId());
            $this->assertSame(0, $entity->getHierarchyDepth());
            $this->assertFalse($entity->isPlaceholder());
        }

        foreach ($this->defs as $definition) {
            $definition = $em->find(AttributeDefinition::class, $definition->getId());
            $this->assertFalse($definition->isHierarchical());
            $this->assertFalse($definition->isAutoFillParents());
        }

        $this->assertSame(
            [$this->defs['ville']->getId() => Attribute::ORIGIN_HUMAN],
            $this->attributesOf($asset),
            'The values filled by the hierarchy are removed',
        );
        $this->assertFalse($em->find(EntityList::class, $this->geo->getId())->isHierarchy());
    }

    /**
     * @return array<string, int> definition ID => origin
     */
    private function attributesOf(Asset $asset): array
    {
        $em = self::getEntityManager();
        $em->clear();
        $attributes = [];
        foreach ($em->getRepository(Attribute::class)->findBy(['asset' => $asset->getId()]) as $attribute) {
            $attributes[$attribute->getDefinition()->getId()] = $attribute->getOrigin();
        }

        return $attributes;
    }
}
