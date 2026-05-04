<?php

namespace App\Tests\Attribute;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Tests\Search\AbstractSearchTest;

class AttributeEntityTest extends AbstractSearchTest
{
    public function testAttributeEntityMerge(): void
    {
        $em = self::getEntityManager();

        $workspace = $this->getOrCreateDefaultWorkspace(['no_flush']);
        $em->persist($workspace);

        $list = new EntityList();
        $list->setName('list1');
        $list->setWorkspace($workspace);
        $em->persist($list);

        $entity1 = new AttributeEntity();
        $entity1->setList($list);
        $entity1->setValue('ae1');
        $entity1->setSynonyms([
            'en' => ['ae1en1', 'ae1en2'],
            'fr' => ['ae1fr1', 'ae1fr2'],
        ]);
        $em->persist($entity1);

        $entity2 = new AttributeEntity();
        $entity2->setList($list);
        $entity2->setValue('ae2');
        $entity2->setSynonyms([
            'en' => ['ae2en1', 'ae2en2'],
            'fr' => ['ae2fr1', 'ae2fr2'],
        ]);
        $em->persist($entity2);

        $definitionSingle = $this->createAttributeDefinition([
            'name' => 'Single',
            'type' => EntityAttributeType::getName(),
            'no_flush' => true,
        ]);

        $definitionMany = $this->createAttributeDefinition([
            'name' => 'Many',
            'type' => EntityAttributeType::getName(),
            'multiple' => true,
            'no_flush' => true,
        ]);

        $this->createAsset([
            'title' => 'Asset1',
            'attributes' => [
                [
                    'definition' => $definitionSingle,
                    'value' => $entity1->getValue(),
                ],
                [
                    'definition' => $definitionMany,
                    'value' => $entity1->getValue(),
                ],
                [
                    'definition' => $definitionMany,
                    'value' => $entity2->getValue(),
                ],
            ],
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

    }
}
