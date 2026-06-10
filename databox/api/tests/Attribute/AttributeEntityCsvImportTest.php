<?php

declare(strict_types=1);

namespace App\Tests\Attribute;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Service\Asset\Attribute\AttributeEntity\Importer\CsvAttributeEntityImporter;
use App\Tests\Search\AbstractSearchTest;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeEntityCsvImportTest extends AbstractSearchTest
{
    public function testImportCreatesAndUpdatesEntities(): void
    {
        $em = self::getEntityManager();

        $workspace = $this->getOrCreateDefaultWorkspace(['no_flush' => true]);
        $em->persist($workspace);

        $list = new EntityList();
        $list->setName('list-csv-import');
        $list->setWorkspace($workspace);
        $em->persist($list);

        $entityById = new AttributeEntity();
        $entityById->setList($list);
        $entityById->setValue('old-value-by-id');
        $entityById->setExternalId('old-id');
        $em->persist($entityById);

        $entityByExternalId = new AttributeEntity();
        $entityByExternalId->setList($list);
        $entityByExternalId->setValue('old-value-by-external-id');
        $entityByExternalId->setExternalId('external-2');
        $em->persist($entityByExternalId);

        $em->flush();

        /** @var CsvAttributeEntityImporter $importer */
        $importer = self::getContainer()->get(CsvAttributeEntityImporter::class);

        $importer->import($list, sprintf(<<<CSV
id,value,external_id,color,status,translation_en,translation_fr-FR
%s,updated-by-id,updated-external-id,#ffffff,1,Updated EN,Updated FR
,updated-by-external-id,external-2,#000000,2,Updated External EN,Updated External FR
,new-value,new-external-id,#336699,0,New EN,New FR
CSV, $entityById->getId()));

        $em->clear();

        $repository = $em->getRepository(AttributeEntity::class);

        /** @var AttributeEntity $updatedById */
        $updatedById = $repository->find($entityById->getId());
        self::assertSame('updated-by-id', $updatedById->getValue());
        self::assertSame('updated-external-id', $updatedById->getExternalId());
        self::assertSame('#ffffff', $updatedById->getColor());
        self::assertSame(1, $updatedById->getStatus());
        self::assertSame(['en' => 'Updated EN', 'fr_fr' => 'Updated FR'], $updatedById->getTranslations());

        /** @var AttributeEntity $updatedByExternalId */
        $updatedByExternalId = $repository->findOneBy([
            'list' => $list->getId(),
            'externalId' => 'external-2',
        ]);
        self::assertSame('updated-by-external-id', $updatedByExternalId->getValue());
        self::assertSame('#000000', $updatedByExternalId->getColor());
        self::assertSame(2, $updatedByExternalId->getStatus());
        self::assertSame(['en' => 'Updated External EN', 'fr_fr' => 'Updated External FR'], $updatedByExternalId->getTranslations());

        /** @var AttributeEntity $created */
        $created = $repository->findOneBy([
            'list' => $list->getId(),
            'externalId' => 'new-external-id',
        ]);
        self::assertNotNull($created);
        self::assertSame('new-value', $created->getValue());
        self::assertSame(0, $created->getStatus());
        self::assertSame('#336699', $created->getColor());
        self::assertSame(['en' => 'New EN', 'fr_fr' => 'New FR'], $created->getTranslations());
    }

    public function testImportRejectsUnsupportedHeader(): void
    {
        $list = $this->createEntityList();

        /** @var CsvAttributeEntityImporter $importer */
        $importer = self::getContainer()->get(CsvAttributeEntityImporter::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported header "foo" in CSV data');

        $importer->import($list, <<<'CSV'
value,foo
hello,bar
CSV);
    }

    public function testImportRejectsCsvWithoutValueHeader(): void
    {
        $list = $this->createEntityList();

        /** @var CsvAttributeEntityImporter $importer */
        $importer = self::getContainer()->get(CsvAttributeEntityImporter::class);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing required header "value" in CSV data');

        $importer->import($list, <<<'CSV'
id,external_id
1,ext-1
CSV);
    }

    public function testImportRejectsValidationErrorsAndDoesNotFlushAnyRow(): void
    {
        $em = self::getEntityManager();
        $list = $this->createEntityList();

        /** @var CsvAttributeEntityImporter $importer */
        $importer = self::getContainer()->get(CsvAttributeEntityImporter::class);

        try {
            $importer->import($list, <<<'CSV'
value,color
valid-row,#ffffff
invalid-row,not-a-color
CSV);
            self::fail('Expected BadRequestHttpException was not thrown.');
        } catch (BadRequestHttpException $e) {
            self::assertStringContainsString('CSV data contains validation errors:', $e->getMessage());
            self::assertStringContainsString('Line 3', $e->getMessage());
            self::assertStringContainsString('color:', $e->getMessage());
        }

        $em->clear();
        $rows = $em->getRepository(AttributeEntity::class)->findBy([
            'list' => $list->getId(),
        ]);

        self::assertCount(0, $rows);
    }

    private function createEntityList(): EntityList
    {
        $em = self::getEntityManager();

        $workspace = $this->getOrCreateDefaultWorkspace(['no_flush' => true]);

        $list = new EntityList();
        $list->setName('list-'.uniqid('', true));
        $list->setWorkspace($workspace);
        $em->persist($list);
        $em->flush();

        return $list;
    }
}
