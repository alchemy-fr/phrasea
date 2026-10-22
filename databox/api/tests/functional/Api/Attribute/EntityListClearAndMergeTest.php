<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Service\Storage\FileManager;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Database side effects of POST /entity-lists/{id}/clear and PUT /attribute-entities/{id}/merge.
 * Both handlers also patch the Elasticsearch documents (covered by Attribute\AttributeEntityTest),
 * hence the search test case.
 */
final class EntityListClearAndMergeTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;

    /**
     * @return string[] the attribute values of the asset, sorted
     */
    private function assetValues(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        $values = array_map(
            fn (Attribute $attribute): string => $attribute->getValue(),
            $em->getRepository(Attribute::class)->findBy(['asset' => $assetId]),
        );
        sort($values);

        return $values;
    }

    /**
     * @return string the storage path of the image file
     */
    private function attachImage(AttributeEntity $entity): string
    {
        $em = self::getEntityManager();
        $entity = $em->find(AttributeEntity::class, $entity->getId());
        $path = sprintf('test-images/%s.png', uniqid());
        self::getService(FileStorageManager::class)->store($path, 'image');

        $imageSet = new ImageSet();
        $imageSet->setWorkspace($entity->getWorkspace());
        $imageSet->setImageFile(self::getService(FileManager::class)->createFile(File::STORAGE_S3_MAIN, $path, 'image/png', 5, 'image.png', $entity->getWorkspace()));
        $entity->setImage($imageSet);
        $em->flush();

        return $path;
    }

    private function assertImageIsDeleted(string $path): void
    {
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->getRepository(File::class)->findOneBy(['path' => $path]), 'the image file is removed');
        $this->assertFalse(self::getService(FileStorageManager::class)->has($path), 'the image is removed from the storage');
    }

    public function testClearAndMergeRemoveTheImagesOfTheRemovedValues(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'People']);
        $alice = $this->createEntity($list, 'Alice');
        $alicia = $this->createEntity($list, 'Alicia');
        $bob = $this->createEntity($list, 'Bob');
        $this->createAttributeDefinition(['name' => 'Person', 'type' => EntityAttributeType::NAME, 'list' => $list]);
        $aliceImage = $this->attachImage($alice);
        $aliciaImage = $this->attachImage($alicia);
        $bobImage = $this->attachImage($bob);

        $this->api('PUT', '/attribute-entities/'.$alice->getId().'/merge', self::USER, [
            'ids' => [$alicia->getId()],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertImageIsDeleted($aliciaImage);
        $this->assertTrue(self::getService(FileStorageManager::class)->has($aliceImage), 'the main value keeps its image');

        $this->api('POST', '/entity-lists/'.$list->getId().'/clear', self::USER);
        $this->assertResponseIsSuccessful();
        $this->assertImageIsDeleted($aliceImage);
        $this->assertImageIsDeleted($bobImage);
        $this->assertSame([], self::getEntityManager()->getRepository(ImageSet::class)->findAll());
    }

    public function testClearRemovesTheValuesAndTheAttributesReferencingThem(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Colors']);
        $otherList = $this->createEntityList(['name' => 'Shapes']);
        $red = $this->createEntity($list, 'Red');
        $square = $this->createEntity($otherList, 'Square');
        $color = $this->createAttributeDefinition(['name' => 'Color', 'type' => EntityAttributeType::NAME, 'list' => $list]);
        $shape = $this->createAttributeDefinition(['name' => 'Shape', 'type' => EntityAttributeType::NAME, 'list' => $otherList]);
        $title = $this->createAttributeDefinition(['name' => 'Title']);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $color, 'value' => $red->getId()],
                ['definition' => $shape, 'value' => $square->getId()],
                ['definition' => $title, 'value' => 'Kept title'],
            ],
        ]);

        $this->api('POST', '/entity-lists/'.$list->getId().'/clear', self::USER);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['id' => $list->getId(), 'name' => 'Colors']);

        $expected = [$square->getId(), 'Kept title'];
        sort($expected);
        $this->assertSame($expected, $this->assetValues($asset->getId()));
        $em = self::getEntityManager();
        $this->assertNull($em->find(AttributeEntity::class, $red->getId()));
        $this->assertNotNull($em->find(AttributeEntity::class, $square->getId()), 'another list is untouched');
        $this->assertNotNull($em->find($color::class, $color->getId()), 'the definition is kept');
    }

    public function testMergeReplacesTheMergedValuesAndCollectsTheirLabelsAsSynonyms(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $list = $this->createEntityList(['name' => 'Colors']);
        $otherList = $this->createEntityList(['name' => 'Shapes']);
        $red = $this->createEntity($list, 'Red', [
            'synonyms' => ['en' => ['Crimson']],
        ]);
        $scarlet = $this->createEntity($list, 'Scarlet', [
            'translations' => ['fr' => 'Écarlate'],
            'synonyms' => ['en' => ['Vermilion', 'Crimson']],
        ]);
        $ruby = $this->createEntity($list, 'Ruby');
        $square = $this->createEntity($otherList, 'Square');
        $color = $this->createAttributeDefinition(['name' => 'Color', 'type' => EntityAttributeType::NAME, 'list' => $list]);
        $colors = $this->createAttributeDefinition(['name' => 'Colors', 'type' => EntityAttributeType::NAME, 'list' => $list, 'multiple' => true]);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $color, 'value' => $scarlet->getId()],
                ['definition' => $colors, 'value' => $ruby->getId(), 'position' => 0],
                ['definition' => $colors, 'value' => $red->getId(), 'position' => 1],
            ],
        ]);

        $response = $this->api('PUT', '/attribute-entities/'.$red->getId().'/merge', self::USER, [
            // an entity of another list is ignored
            'ids' => [$scarlet->getId(), $ruby->getId(), $square->getId()],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame($red->getId(), $data['id']);
        $this->assertSame('Red', $data['value']);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(AttributeEntity::class, $scarlet->getId()));
        $this->assertNull($em->find(AttributeEntity::class, $ruby->getId()));
        $this->assertNotNull($em->find(AttributeEntity::class, $square->getId()));

        /** @var AttributeEntity $main */
        $main = $em->find(AttributeEntity::class, $red->getId());
        $synonyms = $main->getSynonyms();
        $this->assertEqualsCanonicalizing(['Scarlet', 'Ruby'], $synonyms['_'], 'the merged values become synonyms');
        $this->assertEqualsCanonicalizing(['Crimson', 'Vermilion'], $synonyms['en'], 'synonyms are merged without duplicates');
        $this->assertSame(['Écarlate'], $synonyms['fr'], 'translations become synonyms of their locale');

        // Every attribute referencing a merged entity now references the main one
        $this->assertSame(array_fill(0, 3, $red->getId()), $this->assetValues($asset->getId()));
    }
}
