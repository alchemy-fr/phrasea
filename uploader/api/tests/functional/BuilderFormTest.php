<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\FormSchema;
use App\Entity\Target;
use App\Tests\Functional\Databox\FakeDataboxClient;
use PHPUnit\Framework\Attributes\DataProvider;

class BuilderFormTest extends AbstractUploaderTestCase
{
    private const string WORKSPACE_ID = '7e5ab1c2-0a7e-4b8a-9d2c-5c1b2f0e6a01';
    private const string LIST_ID = '2b3c4d5e-6f70-4182-93a4-b5c6d7e8f901';
    private const string ENTITY_ID = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
    private const string OTHER_ENTITY_ID = 'b1b2c3d4-e5f6-4789-8abc-def012345678';

    public static function definition(): array
    {
        return [
            'version' => 1,
            'workspaceId' => self::WORKSPACE_ID,
            'fields' => [
                ['id' => 's', 'source' => 'layout', 'kind' => 'section', 'label' => ['en' => 'Info']],
                [
                    'id' => 'title',
                    'source' => 'attribute',
                    'key' => 'title',
                    'type' => 'text',
                    'required' => true,
                    'attribute' => ['id' => 'x', 'slug' => 'title', 'databoxType' => 'text'],
                    'label' => ['en' => 'Title', 'fr' => 'Titre'],
                    'constraints' => ['maxLength' => 10],
                ],
                [
                    'id' => 'keywords',
                    'source' => 'attribute',
                    'key' => 'keywords',
                    'type' => 'entity',
                    'multiple' => true,
                    'attribute' => ['id' => 'y', 'slug' => 'keywords', 'databoxType' => 'entity', 'entityListId' => self::LIST_ID],
                ],
                ['id' => 'count', 'source' => 'custom', 'key' => 'count', 'type' => 'number', 'constraints' => ['max' => 5]],
                ['id' => 'origin', 'source' => 'custom', 'key' => 'origin', 'type' => 'text', 'hidden' => true, 'defaultValue' => 'web'],
                ['id' => 'notify', 'source' => 'builtin', 'key' => '__notify'],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->databox = new FakeDataboxClient([
            '/attribute-entities' => static fn (array $query): array => [
                'member' => array_values(array_map(
                    static fn (string $id): array => ['id' => $id, 'value' => 'Value '.$id, 'translations' => ['fr' => 'Valeur']],
                    array_intersect($query['ids'] ?? [self::ENTITY_ID], [self::ENTITY_ID]),
                )),
            ],
        ]);
    }

    private function createSchema(?array $definition = null, ?Target $target = null): FormSchema
    {
        $em = self::getEntityManager();
        if (null !== $target) {
            $em->persist($target);
        }
        $schema = new FormSchema();
        $schema->setTarget($target ?? $this->getOrCreateDefaultTarget());
        $schema->setFormat(FormSchema::FORMAT_BUILDER);
        $schema->setData($definition ?? self::definition());
        $em->persist($schema);
        $em->flush();

        return $schema;
    }

    public function testPostBuilderSchema(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form-schemas', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'format' => FormSchema::FORMAT_BUILDER,
            'data' => self::definition(),
        ]);
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(FormSchema::FORMAT_BUILDER, $response->toArray()['format']);
    }

    public function testPostInvalidBuilderSchema(): void
    {
        $definition = self::definition();
        $definition['fields'][2]['attribute']['entityListId'] = null;

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form-schemas', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'format' => FormSchema::FORMAT_BUILDER,
            'data' => $definition,
        ]);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame('data.fields.2.attribute.entityListId', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testLiFormIsTheDefaultFormat(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form-schemas', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => ['type' => 'object'],
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(FormSchema::FORMAT_LIFORM, $response->toArray()['format']);
    }

    public function testValidate(): void
    {
        $this->createSchema();

        $errors = $this->validate([
            'title' => 'A title',
            'keywords' => [self::ENTITY_ID],
            'count' => '3',
        ]);
        $this->assertSame([], $errors);

        $errors = $this->validate([
            'title' => 'A much too long title',
            'keywords' => [self::ENTITY_ID, self::OTHER_ENTITY_ID, 'not-a-uuid'],
            'count' => 8,
        ]);
        $this->assertSame(['title', 'keywords', 'count'], array_keys($errors));
        $this->assertCount(2, $errors['keywords'], json_encode($errors['keywords']));

        $this->assertSame(['title' => ['This value should not be blank.']], $this->validate([]));
    }

    public function testValidateWhenDataboxDeniesAccess(): void
    {
        $this->createSchema();
        $this->databox = new FakeDataboxClient(['/attribute-entities' => 403]);

        $errors = $this->validate(['title' => 'A title', 'keywords' => [self::ENTITY_ID]]);
        $this->assertSame(['keywords' => ['The values could not be checked against Databox.']], $errors);
    }

    public function testCommitKeepsTheFormFieldsOnly(): void
    {
        $target = new Target();
        $target->setName('Pull mode');
        $target->setSlug('pull-mode');
        // No target URL: the commit is not pushed to a consumer
        $target->setTargetUrl(null);
        $this->createSchema(target: $target);
        $asset = $this->createAsset($target);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/commit', [
            'files' => [$asset->getId()],
            'formData' => [
                'title' => ' A title ',
                'keywords' => self::ENTITY_ID,
                'count' => '2',
                'origin' => 'forged',
                'secret_attribute' => 'injected',
                '__notify' => true,
            ],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));

        $em = self::getEntityManager();
        $em->clear();
        $commit = $em->getRepository(Commit::class)->findOneBy(['target' => $target->getId()]);
        $this->assertInstanceOf(Commit::class, $commit);
        $this->assertTrue($commit->isNotify());
        $this->assertSame([
            'title' => 'A title',
            'keywords' => [self::ENTITY_ID],
            'count' => 2,
            'origin' => 'web',
        ], $commit->getFormData());
    }

    public function testFieldOptions(): void
    {
        $this->createSchema();
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/form-fields/keywords/options?query=val');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([
            'member' => [['value' => self::ENTITY_ID, 'label' => 'Value '.self::ENTITY_ID, 'color' => null, 'emoji' => null]],
            'hasMore' => false,
        ], $response->toArray());
        $this->assertSame(['list' => self::LIST_ID, 'limit' => 30, 'page' => 1, 'query' => 'val'], $this->databox->calls[0]['query']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/form-fields/title/options');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/form-fields/unknown/options');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(null, 'GET', '/targets/'.$targetId.'/form-fields/keywords/options');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBuilderCatalog(): void
    {
        $this->databox = new FakeDataboxClient([
            '/workspaces' => ['member' => [['id' => self::WORKSPACE_ID, 'name' => 'ws', 'displayName' => 'Workspace', 'enabledLocales' => ['en', 'fr']]]],
            '/attribute-definitions' => ['member' => [
                ['id' => 'a', 'slug' => 'title', 'name' => 'Title', 'type' => 'text', 'multiple' => false, 'translations' => ['name' => ['fr' => 'Titre']]],
                ['id' => 'b', 'slug' => 'keywords', 'name' => 'Keywords', 'type' => 'entity', 'multiple' => true, 'entityList' => ['id' => self::LIST_ID, 'name' => 'Keywords']],
                ['id' => 'c', 'slug' => 'status', 'name' => 'Status', 'type' => 'asset_status'],
                ['id' => 'd', 'slug' => 'locked', 'name' => 'Locked', 'type' => 'text', 'editable' => false],
            ]],
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/workspaces');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([['id' => self::WORKSPACE_ID, 'name' => 'Workspace', 'enabledLocales' => ['en', 'fr']]], $response->toArray());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/workspaces/'.self::WORKSPACE_ID.'/fields');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $fields = $response->toArray();
        $this->assertSame(['title', 'keywords'], array_column($fields, 'slug'));
        $this->assertSame(['fr' => 'Titre'], $fields[0]['label']);
        $this->assertSame('entity', $fields[1]['type']);
        $this->assertSame(self::LIST_ID, $fields[1]['entityList']['id']);
        $this->assertSame('/attribute-definitions', $this->databox->calls[1]['path']);
        $this->assertSame(['workspaceId' => self::WORKSPACE_ID, 'enabled' => 'true', 'target' => 1, 'limit' => 100, 'page' => 1], $this->databox->calls[1]['query']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/databox/workspaces');
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertCount(2, $this->databox->calls, 'Databox must not be called for a user who cannot edit forms');
    }

    #[DataProvider('destinations')]
    public function testTargetDestination(?string $destination, ?array $expected): void
    {
        $this->databox = new FakeDataboxClient([
            '/collections/c1' => ['id' => 'c1', 'absoluteDisplayName' => 'Root / Uploads', 'workspace' => ['id' => self::WORKSPACE_ID]],
        ]);
        $target = new Target();
        $target->setName('Destination');
        $target->setSlug('destination');
        $target->setDefaultDestination($destination);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/targets/'.$target->getId().'/destination');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['collection' => $expected], $response->toArray());
    }

    public static function destinations(): array
    {
        return [
            'none' => [null, null],
            'collection' => ['c1', ['id' => 'c1', 'title' => 'Root / Uploads', 'workspaceId' => self::WORKSPACE_ID]],
            'not a Databox collection' => ['phraseanet-base-1', null],
        ];
    }

    public function testBuilderCatalogDataboxErrors(): void
    {
        $this->databox = new FakeDataboxClient(['/workspaces' => 403, '/workspaces/broken/fields' => 500, '/attribute-definitions' => 500]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/workspaces');
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/workspaces/unknown');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/databox/workspaces/broken/fields');
        $this->assertEquals(502, $response->getStatusCode());
    }

    private function validate(array $data): array
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => $data,
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray()['errors'];
    }

    private function createAsset(Target $target): Asset
    {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setMimeType('image/jpeg');
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId(KeycloakClientTestMock::ADMIN_UID);
        $em = self::getEntityManager();
        $em->persist($asset);
        $em->flush();

        return $asset;
    }
}
