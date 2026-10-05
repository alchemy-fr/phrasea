<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /entity-lists: the controlled vocabularies of a workspace, with their import/export.
 * The clear operation, which needs Elasticsearch, is covered by EntityListClearTest.
 */
final class EntityListTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member.
     */
    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
    }

    private function listNames(?string $userId, array $query = []): array
    {
        $response = $this->api('GET', '/entity-lists', $userId, options: ['query' => $query]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['hydra:member'], 'name');
    }

    private function listPayload(array $data = []): array
    {
        return array_merge([
            'workspace' => '/workspaces/'.$this->workspace->getId(),
            'name' => 'Colors',
        ], $data);
    }

    /**
     * @return array<string, AttributeEntity>
     */
    private function entitiesByValue(string $listId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        $entities = [];
        foreach ($em->getRepository(AttributeEntity::class)->findBy(['list' => $listId]) as $entity) {
            $entities[$entity->getValue()] = $entity;
        }

        return $entities;
    }

    private function export(string $userId, EntityList $list, array $data): string
    {
        $this->api('POST', '/entity-lists/'.$list->getId().'/export', $userId, $data);

        // The export is a StreamedResponse: its content is only captured by the kernel browser
        return static::getClient()->getInternalResponse()->getContent();
    }

    public function testListFiltersAndOrder(): void
    {
        $this->setUpScene();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $this->createEntityList(['name' => 'Colors']);
        $this->createEntityList(['name' => 'Shapes']);
        $this->createEntityList(['name' => 'Car colors', 'workspace' => $otherWorkspace]);

        $this->assertSame(['Colors', 'Shapes'], $this->listNames(self::USER, [
            'workspace' => $this->workspace->getId(),
            'order' => ['name' => 'asc'],
        ]));
        $this->assertSame(['Car colors', 'Colors'], $this->listNames(self::USER, [
            'name' => 'COLOR',
            'order' => ['name' => 'asc'],
        ]));
        $this->assertSame(['Shapes', 'Colors', 'Car colors'], $this->listNames(self::USER, [
            'order' => ['name' => 'desc'],
        ]));
    }

    public function testListIsRestrictedToReadableWorkspaces(): void
    {
        $this->markTestIncomplete('BUG: GET /entity-lists returns the lists of every workspace, to anyone: no security, provider nor Doctrine extension restricts EntityList (src/Entity/Core/EntityList.php:44 "new GetCollection()")');

        $this->setUpScene();
        $this->createEntityList(['name' => 'Colors']);
        $this->createEntityList(['name' => 'Foreign', 'workspace' => $this->createOtherWorkspace()]);

        $this->assertSame(['Colors'], $this->listNames(self::OTHER));
        $this->api('GET', '/entity-lists');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetItem(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors', 'allowNewValues' => true]);
        $definition = $this->createAttributeDefinition([
            'name' => 'Color',
            'type' => EntityAttributeType::NAME,
            'list' => $list,
        ]);
        self::getEntityManager()->clear();

        $response = $this->api('GET', '/entity-lists/'.$list->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'entity-list',
            'id' => $list->getId(),
            'name' => 'Colors',
            'allowNewValues' => true,
            'approveNewValues' => false,
            'withEmojis' => false,
            'withColors' => false,
            'withTranslations' => false,
            'withSynonyms' => false,
        ]);
        $definitions = $response->toArray()['definitions'];
        $this->assertCount(1, $definitions);
        $this->assertSame('Color', $definitions[0]['name']);
        $this->assertSame('/attribute-definitions/'.$definition->getId(), $definitions[0]['@id']);
    }

    public function testGetItemIsRestricted(): void
    {
        $this->markTestIncomplete('BUG: GET /entity-lists/{id} has no security expression (src/Entity/Core/EntityList.php:40 "new Get()"): anyone reads the lists of any workspace');

        $this->setUpScene();
        $foreign = $this->createEntityList(['name' => 'Foreign', 'workspace' => $this->createOtherWorkspace()]);

        $this->api('GET', '/entity-lists/'.$foreign->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/entity-lists/'.$foreign->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreate(): void
    {
        $this->setUpScene();

        $response = $this->api('POST', '/entity-lists', self::USER, $this->listPayload([
            'allowNewValues' => true,
            'approveNewValues' => true,
            'withColors' => true,
            'withTranslations' => true,
        ]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'Colors',
            'allowNewValues' => true,
            'approveNewValues' => true,
            'withEmojis' => false,
            'withColors' => true,
            'withTranslations' => true,
            'withSynonyms' => false,
            'definitions' => [],
        ]);

        $list = self::getEntityManager()->find(EntityList::class, $response->toArray()['id']);
        $this->assertSame(self::USER, $list->getOwnerId(), 'the creator owns the list');
        $this->assertSame($this->workspace->getId(), $list->getWorkspaceId());
    }

    public function testCreateRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();

        $this->api('POST', '/entity-lists', null, $this->listPayload());
        $this->assertResponseStatusCodeSame(401);

        $this->api('POST', '/entity-lists', self::OTHER, $this->listPayload());
        $this->assertResponseStatusCodeSame(403);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->api('POST', '/entity-lists', self::OTHER, $this->listPayload());
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateValidation(): void
    {
        $this->setUpScene();
        $this->createEntityList(['name' => 'Colors']);

        $this->api('POST', '/entity-lists', self::USER, $this->listPayload());
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['message' => 'This entity type already exists in the workspace.']]]);

        $this->api('POST', '/entity-lists', self::USER, $this->listPayload(['name' => '']));
        $this->assertResponseStatusCodeSame(422);

        $this->api('POST', '/entity-lists', self::USER, $this->listPayload(['name' => str_repeat('a', EntityList::TYPE_LENGTH + 1)]));
        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdate(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $iri = '/entity-lists/'.$list->getId();

        $this->api('PUT', $iri, self::USER, ['name' => 'Palette', 'withEmojis' => true]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Palette', 'withEmojis' => true]);

        $this->api('PATCH', $iri, self::USER, ['withSynonyms' => true]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Palette', 'withEmojis' => true, 'withSynonyms' => true]);
    }

    public function testWritesRequireTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $iri = '/entity-lists/'.$list->getId();

        $this->api('PUT', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', $iri.'/import', self::OTHER, ['format' => 'raw', 'data' => 'Red']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', $iri.'/export', self::OTHER, ['format' => 'csv']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', $iri.'/clear', self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteRemovesTheValuesAndTheEntityDefinitions(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $red = $this->createEntity($list, 'Red');
        $definition = $this->createAttributeDefinition([
            'name' => 'Color',
            'type' => EntityAttributeType::NAME,
            'list' => $list,
        ]);
        $kept = $this->createAttributeDefinition(['name' => 'Kept']);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $definition, 'value' => $red->getId()],
                ['definition' => $kept, 'value' => 'Kept value'],
            ],
        ]);
        $assetId = $asset->getId();
        self::getEntityManager()->clear();

        $this->api('DELETE', '/entity-lists/'.$list->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(EntityList::class, $list->getId()));
        $this->assertNull($em->find(AttributeEntity::class, $red->getId()));
        $this->assertNull($em->find(AttributeDefinition::class, $definition->getId()), 'the definitions using the list are deleted');
        $this->assertSame(['Kept value'], array_map(
            fn (Attribute $attribute): string => $attribute->getValue(),
            $em->getRepository(Attribute::class)->findBy(['asset' => $assetId]),
        ));
    }

    public function testRawImportAddsTheMissingValues(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $this->createEntity($list, 'Red');

        $this->api('POST', '/entity-lists/'.$list->getId().'/import', self::USER, [
            'format' => 'raw',
            'data' => "Red\n  Blue \n\nGreen\nBlue\n",
        ]);
        $this->assertResponseIsSuccessful();

        $entities = $this->entitiesByValue($list->getId());
        $this->assertEqualsCanonicalizing(['Red', 'Blue', 'Green'], array_keys($entities));
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $entities['Blue']->getStatus());
    }

    public function testCsvImport(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $red = $this->createEntity($list, 'Red');

        $this->api('POST', '/entity-lists/'.$list->getId().'/import', self::USER, [
            'format' => 'csv',
            'data' => sprintf("id,value,color,translation_fr\n%s,Dark red,#880000,Rouge foncé\n,Blue,#0000ff,Bleu\n", $red->getId()),
        ]);
        $this->assertResponseIsSuccessful();

        $entities = $this->entitiesByValue($list->getId());
        $this->assertEqualsCanonicalizing(['Dark red', 'Blue'], array_keys($entities));
        $this->assertSame($red->getId(), $entities['Dark red']->getId());
        $this->assertSame('#880000', $entities['Dark red']->getColor());
        $this->assertSame(['fr' => 'Bleu'], $entities['Blue']->getTranslations());

        $this->api('POST', '/entity-lists/'.$list->getId().'/import', self::USER, [
            'format' => 'csv',
            'data' => "value,unknown\nYellow,x\n",
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testImportValidation(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $iri = '/entity-lists/'.$list->getId().'/import';

        $this->api('POST', $iri, self::USER, ['format' => 'xml', 'data' => '<colors/>']);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Unsupported import format "xml".']);

        $this->api('POST', $iri, self::USER, ['format' => 'raw', 'data' => '']);
        $this->assertResponseStatusCodeSame(422);

        $this->api('POST', $iri, self::USER, ['data' => 'Red']);
        $this->assertResponseStatusCodeSame(422);

        // An unknown list is not read before the security check: it is denied
        $this->api('POST', '/entity-lists/9b2a7f5e-0000-4000-8000-000000000000/import', self::USER, ['format' => 'raw', 'data' => 'Red']);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame([], $this->entitiesByValue($list->getId()));
    }

    public function testExport(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $red = $this->createEntity($list, 'Red', ['translations' => ['fr' => 'Rouge']]);
        $blue = $this->createEntity($list, 'Blue');

        $json = $this->export(self::USER, $list, ['format' => 'json']);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="Colors.json"');
        $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $rows);
        $byId = array_column($rows, null, 'id');
        $this->assertSame('Red', $byId[$red->getId()]['value']);
        $this->assertSame(['fr' => 'Rouge'], $byId[$red->getId()]['translations']);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $byId[$blue->getId()]['status']);

        // With a locale, the translated value (or the value) is exported
        $rows = json_decode($this->export(self::USER, $list, ['format' => 'json', 'locale' => 'fr']), true, 512, JSON_THROW_ON_ERROR);
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="Colors-fr.json"');
        $this->assertEqualsCanonicalizing(['Rouge', 'Blue'], array_column($rows, 'value'));
        $this->assertArrayNotHasKey('translations', $rows[0]);

        $liform = json_decode($this->export(self::USER, $list, ['format' => 'liform', 'locale' => 'fr']), true, 512, JSON_THROW_ON_ERROR);
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="Colors-fr.liform.json"');
        $this->assertEqualsCanonicalizing([$red->getId(), $blue->getId()], $liform['enum']);
        $this->assertEqualsCanonicalizing(['Rouge', 'Blue'], $liform['enum_titles']);
    }

    public function testCsvExport(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);
        $red = $this->createEntity($list, 'Red', ['translations' => ['fr' => 'Rouge']]);

        $csv = $this->export(self::USER, $list, ['format' => 'csv']);
        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/csv', static::getClient()->getResponse()->headers->get('content-type'));
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="Colors.csv"');
        $lines = array_map(str_getcsv(...), array_filter(explode("\n", $csv)));
        // one translation column per enabled locale of the workspace (fr, en, de)
        $this->assertSame(['id', 'value', 'emoji', 'color', 'status', 'external_id', 'translation_fr', 'translation_en', 'translation_de'], $lines[0]);
        $this->assertSame([$red->getId(), 'Red', '', '', '0', '', 'Rouge', '', ''], $lines[1]);

        // The "_" locale means "no locale"
        $csv = $this->export(self::USER, $list, ['format' => 'csv', 'locale' => '_']);
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="Colors.csv"');
        $this->assertStringStartsWith('id,value,emoji,color,status,external_id,translation_fr', $csv);
    }

    public function testExportValidation(): void
    {
        $this->setUpScene();
        $list = $this->createEntityList(['name' => 'Colors']);

        $this->export(self::USER, $list, ['format' => 'xml']);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Unsupported export format "xml".']);

        $this->export(self::USER, $list, []);
        $this->assertResponseStatusCodeSame(422);
    }
}
