<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use App\Form\Builder\FieldSource;
use App\Form\Builder\FieldType;
use App\Form\Builder\FormDefinition;
use DoctrineMigrations\Version20261116213000;
use PHPUnit\Framework\TestCase;

// The migrations are not autoloaded
require_once __DIR__.'/../../../migrations/Version20261116213000.php';

class TagsFieldConversionTest extends TestCase
{
    private const string WORKSPACE_ID = '4b1a6ac4-6a52-4d3b-9b4f-0e2f8d1c9a77';
    private const string TAG_ID = '0f8a9c3e-2a51-4f4b-8d61-3c0b8f9e1a20';

    public function testConvertsTheTagsField(): void
    {
        $form = [
            'version' => 1,
            'workspaceId' => strtoupper(self::WORKSPACE_ID),
            'fields' => [
                ['id' => 'f_1', 'source' => 'builtin', 'key' => 'databox_name', 'type' => 'text', 'label' => ['_' => 'Name']],
                [
                    'id' => 'f_2',
                    'source' => 'builtin',
                    'key' => 'databox_tags',
                    'type' => 'tag',
                    'multiple' => true,
                    'required' => true,
                    'widget' => 'checkboxes',
                    'defaultValue' => [self::TAG_ID],
                    'label' => ['_' => 'Tags', 'fr' => 'Étiquettes'],
                    'help' => ['_' => 'Pick some'],
                ],
            ],
        ];

        ['form' => $converted, 'key' => $key, 'warnings' => $warnings] = Version20261116213000::convertForm($form);

        $this->assertSame([], $warnings);
        $this->assertSame('tags', $key);
        $this->assertSame($form['fields'][0], $converted['fields'][0]);
        $this->assertSame([
            'id' => 'f_2',
            'source' => 'attribute',
            'key' => 'tags',
            'type' => 'entity',
            'multiple' => true,
            'required' => true,
            'widget' => 'checkboxes',
            'defaultValue' => [self::TAG_ID],
            'label' => ['_' => 'Tags', 'fr' => 'Étiquettes'],
            'help' => ['_' => 'Pick some'],
            'attribute' => [
                'id' => Version20261116213000::deterministicUuid(self::WORKSPACE_ID, 'tags-definition'),
                'slug' => 'tags',
                'databoxType' => 'entity',
                'translatable' => false,
                'entityListId' => Version20261116213000::deterministicUuid(self::WORKSPACE_ID, 'tags-list'),
                'entityListName' => 'Tags',
            ],
        ], $converted['fields'][1]);

        $field = FormDefinition::fromArray($converted)->getField('tags');
        $this->assertSame(FieldSource::Attribute, $field?->source);
        $this->assertSame(FieldType::Entity, $field->type);
        $this->assertTrue($field->multiple);
        $this->assertSame(Version20261116213000::deterministicUuid(self::WORKSPACE_ID, 'tags-list'), $field->entityListId);
    }

    public function testDeterministicUuidMatchesPostgres(): void
    {
        // SELECT md5('4b1a6ac4-6a52-4d3b-9b4f-0e2f8d1c9a77' || 'tags-list')::uuid
        $this->assertSame('58d3a593-7378-f6c7-97a7-ecd9b0bd460a', Version20261116213000::deterministicUuid(self::WORKSPACE_ID, 'tags-list'));
    }

    public function testFallsBackToTheMigratedSlug(): void
    {
        $form = [
            'version' => 1,
            'workspaceId' => self::WORKSPACE_ID,
            'fields' => [
                ['id' => 'f_1', 'source' => 'custom', 'key' => 'tags', 'type' => 'text', 'label' => []],
                ['id' => 'f_2', 'source' => 'builtin', 'key' => 'databox_tags', 'type' => 'tag', 'multiple' => true, 'label' => []],
            ],
        ];

        ['form' => $converted, 'key' => $key, 'warnings' => $warnings] = Version20261116213000::convertForm($form);

        $this->assertSame('tagsmigrated', $key);
        $this->assertCount(1, $warnings);
        $this->assertSame('tagsmigrated', $converted['fields'][1]['key']);
        $this->assertSame('tagsmigrated', $converted['fields'][1]['attribute']['slug']);
        FormDefinition::fromArray($converted);
    }

    public function testLeavesTheFieldOutWithoutWorkspace(): void
    {
        $form = [
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_1', 'source' => 'builtin', 'key' => 'databox_tags', 'type' => 'tag', 'multiple' => true, 'label' => []],
                ['id' => 'f_2', 'source' => 'custom', 'key' => 'title', 'type' => 'text', 'label' => []],
            ],
        ];

        ['form' => $converted, 'key' => $key, 'warnings' => $warnings] = Version20261116213000::convertForm($form);

        $this->assertNull($key);
        $this->assertCount(1, $warnings);
        $this->assertSame([$form['fields'][1]], $converted['fields']);
        FormDefinition::fromArray($converted);
    }

    public function testKeepsAFormWithoutTags(): void
    {
        $form = [
            'version' => 1,
            'workspaceId' => self::WORKSPACE_ID,
            'fields' => [
                ['id' => 'f_1', 'source' => 'custom', 'key' => 'databox_tags_note', 'type' => 'text', 'label' => ['_' => 'databox_tags']],
            ],
        ];

        $this->assertSame(['form' => $form, 'key' => null, 'warnings' => []], Version20261116213000::convertForm($form));
    }

    public function testConvertsTheData(): void
    {
        $this->assertSame(['a' => 1], Version20261116213000::convertData(['a' => 1], 'tags'));
        $this->assertSame(['a' => 1, 'tags' => ['x', 'y']], Version20261116213000::convertData(['a' => 1, 'databox_tags' => ['x', 'y']], 'tags'));
        $this->assertSame(['a' => 1], Version20261116213000::convertData(['a' => 1, 'databox_tags' => []], 'tags'));
        $this->assertSame(['tags' => ['y', 'x']], Version20261116213000::convertData(['tags' => 'y', 'databox_tags' => ['x', 'y']], 'tags'));
        $this->assertSame(['tagsmigrated' => ['x']], Version20261116213000::convertData(['databox_tags' => ['x']], 'tagsmigrated'));
    }
}
