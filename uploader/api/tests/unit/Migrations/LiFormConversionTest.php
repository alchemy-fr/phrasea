<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use App\Form\Builder\FormDefinition;
use DoctrineMigrations\Version20261111120000;
use PHPUnit\Framework\TestCase;

// The migrations are not autoloaded
require_once __DIR__.'/../../../migrations/Version20261111120000.php';

class LiFormConversionTest extends TestCase
{
    public function testConvertsTheFixture(): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/liform-schema.json'), true, 512, JSON_THROW_ON_ERROR);

        ['definition' => $definition, 'warnings' => $warnings] = Version20261111120000::convertLiForm($schema);

        $this->assertSame([], $warnings);
        $this->assertValid($definition);
        $this->assertSame(1, $definition['version']);
        $this->assertNull($definition['workspaceId']);
        $this->assertSame([
            ['source' => 'custom', 'key' => 'album', 'type' => 'text', 'label' => ['_' => 'Album'], 'required' => true],
            [
                'source' => 'custom',
                'key' => 'dest',
                'type' => 'choice',
                'widget' => 'select',
                'choices' => [
                    ['value' => 'One', 'label' => ['_' => 'One']],
                    ['value' => 'Two', 'label' => ['_' => 'Two']],
                ],
                'label' => ['_' => 'Choose a destination'],
            ],
            ['source' => 'custom', 'key' => 'description', 'type' => 'text', 'label' => ['_' => 'Description']],
            ['source' => 'custom', 'key' => 'agreed', 'type' => 'boolean', 'label' => ['_' => 'I agree with the terms'], 'required' => true],
        ], self::withoutIds($definition['fields']));

        $ids = array_column($definition['fields'], 'id');
        $this->assertCount(4, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('#^f_[0-9a-z]{8}$#', $id);
        }
    }

    public function testResolvesReferencesAndOrdersTheProperties(): void
    {
        ['definition' => $definition] = Version20261111120000::convertLiForm([
            'type' => 'object',
            'definitions' => [
                'text' => ['type' => 'string', 'maxLength' => 20],
                'when' => ['$ref' => '#/definitions/date'],
                'date' => ['type' => 'string', 'format' => 'date'],
            ],
            'properties' => [
                'second' => ['$ref' => '#/definitions/text', 'title' => 'Second', 'propertyOrder' => 2],
                'first' => [
                    'allOf' => [['$ref' => '#/definitions/text'], ['description' => 'Help']],
                    'title' => 'First',
                    'propertyOrder' => 1,
                ],
                'day' => ['$ref' => '#/definitions/when', 'title' => 'Day', 'propertyOrder' => 3],
                'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 2, 'propertyOrder' => 4],
                'tags' => ['type' => 'array', 'items' => ['enum' => ['a', 'b'], 'enum_titles' => ['A', 'B']], 'widget' => 'choice-multiple-expanded', 'propertyOrder' => 5],
            ],
        ]);

        $this->assertValid($definition);
        $this->assertSame([
            ['source' => 'custom', 'key' => 'first', 'type' => 'text', 'label' => ['_' => 'First'], 'help' => ['_' => 'Help'], 'constraints' => ['maxLength' => 20]],
            ['source' => 'custom', 'key' => 'second', 'type' => 'text', 'label' => ['_' => 'Second'], 'constraints' => ['maxLength' => 20]],
            ['source' => 'custom', 'key' => 'day', 'type' => 'date', 'label' => ['_' => 'Day']],
            ['source' => 'custom', 'key' => 'count', 'type' => 'number', 'label' => [], 'defaultValue' => 2, 'constraints' => ['min' => 1, 'max' => 5]],
            [
                'source' => 'custom',
                'key' => 'tags',
                'type' => 'choice',
                'multiple' => true,
                'widget' => 'checkboxes',
                'choices' => [
                    ['value' => 'a', 'label' => ['_' => 'A']],
                    ['value' => 'b', 'label' => ['_' => 'B']],
                ],
                'label' => [],
            ],
        ], self::withoutIds($definition['fields']));
    }

    public function testBuiltinsAndUnsupportedProperties(): void
    {
        ['definition' => $definition, 'warnings' => $warnings] = Version20261111120000::convertLiForm([
            'type' => 'object',
            'properties' => [
                'databox_name' => ['type' => 'string', 'title' => 'Asset name'],
                '__notify' => ['type' => 'boolean'],
                'databox_tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'address' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                'variant' => ['oneOf' => [['properties' => ['a' => ['type' => 'string']]]]],
                'people' => ['type' => 'array', 'items' => ['type' => 'object']],
                'flags' => ['type' => 'array', 'items' => ['type' => 'boolean']],
                'bad key' => ['type' => 'string'],
                'empty' => ['enum' => ['']],
                'title:fr' => ['type' => 'string', 'title' => 'Titre'],
            ],
        ]);

        $this->assertValid($definition);
        $this->assertSame([
            ['source' => 'builtin', 'key' => 'databox_name', 'type' => 'text', 'label' => ['_' => 'Asset name']],
            ['source' => 'builtin', 'key' => '__notify', 'type' => 'boolean', 'label' => ['en' => 'Notify me when done', 'fr' => 'Me prévenir à la fin du traitement']],
            ['source' => 'custom', 'key' => 'title:fr', 'type' => 'text', 'label' => ['_' => 'Titre']],
        ], self::withoutIds($definition['fields']));

        $this->assertCount(7, $warnings);
        foreach (['databox_tags', 'address', 'variant', 'people', 'flags', 'bad key', 'empty'] as $i => $key) {
            $this->assertStringContainsString('"'.$key.'"', $warnings[$i]);
        }
    }

    public function testMergesTheLiFormSchemasOfTheLocales(): void
    {
        $result = Version20261111120000::mergeSchemas([
            [
                'locale' => 'fr',
                'format' => 'liform',
                'updatedAt' => '2026-01-02 00:00:00',
                'data' => ['properties' => [
                    'album' => ['type' => 'string', 'title' => 'Album FR', 'description' => 'Aide'],
                    'dest' => ['enum' => ['One', 'Two'], 'enum_titles' => ['Un', 'Deux']],
                    'other' => ['type' => 'string', 'title' => 'Ignored: not in the base schema'],
                ]],
            ],
            [
                'locale' => null,
                'format' => 'liform',
                'updatedAt' => '2026-01-01 00:00:00',
                'data' => ['properties' => [
                    'album' => ['type' => 'string', 'title' => 'Album', 'placeholder' => 'Name it'],
                    'dest' => ['enum' => ['One', 'Two'], 'enum_titles' => ['First']],
                ]],
            ],
        ]);

        $this->assertSame([], $result['warnings']);
        $this->assertValid($result['form']);
        $this->assertSame([
            [
                'source' => 'custom',
                'key' => 'album',
                'type' => 'text',
                'label' => ['_' => 'Album', 'fr' => 'Album FR'],
                'placeholder' => ['_' => 'Name it'],
                'help' => ['fr' => 'Aide'],
            ],
            [
                'source' => 'custom',
                'key' => 'dest',
                'type' => 'choice',
                'widget' => 'select',
                'choices' => [
                    ['value' => 'One', 'label' => ['_' => 'First', 'fr' => 'Un']],
                    ['value' => 'Two', 'label' => ['_' => 'Two', 'fr' => 'Deux']],
                ],
                'label' => [],
            ],
        ], self::withoutIds($result['form']['fields']));
    }

    public function testTheMostRecentSchemaIsTheBaseWithoutBuilderNorSchemaForEveryLocale(): void
    {
        $result = Version20261111120000::mergeSchemas([
            ['locale' => 'fr', 'format' => 'liform', 'updatedAt' => '2026-01-01 00:00:00', 'data' => ['properties' => ['a' => ['type' => 'string', 'title' => 'Ancien']]]],
            ['locale' => 'en', 'format' => 'liform', 'updatedAt' => '2026-03-01 00:00:00', 'data' => ['properties' => ['a' => ['type' => 'string', 'title' => 'Recent']]]],
        ]);

        $this->assertValid($result['form']);
        $this->assertSame(['_' => 'Recent', 'fr' => 'Ancien'], $result['form']['fields'][0]['label']);
    }

    public function testMergesLiFormTranslationsIntoTheBuilderForm(): void
    {
        $builder = [
            'version' => 1,
            'workspaceId' => 'ws',
            'fields' => [
                ['id' => 'f_section', 'source' => 'layout', 'kind' => 'section', 'label' => ['_' => 'Info']],
                ['id' => 'f_title', 'source' => 'attribute', 'key' => 'title', 'type' => 'text', 'attribute' => ['id' => 'x', 'slug' => 'title', 'databoxType' => 'text'], 'label' => ['_' => 'Title', 'fr' => 'Titre']],
                ['id' => 'f_dest', 'source' => 'custom', 'key' => 'dest', 'type' => 'choice', 'choices' => [['value' => 'One', 'label' => ['_' => 'One']]], 'label' => ['_' => 'Destination']],
            ],
        ];

        $result = Version20261111120000::mergeSchemas([
            [
                'locale' => null,
                'format' => 'liform',
                'updatedAt' => '2026-05-01 00:00:00',
                'data' => ['properties' => ['title' => ['type' => 'string', 'title' => 'Not the base']]],
            ],
            [
                'locale' => 'de',
                'format' => 'liform',
                'updatedAt' => '2026-05-01 00:00:00',
                'data' => ['properties' => [
                    'title' => ['type' => 'string', 'title' => 'Titel', 'description' => 'Hilfe'],
                    'dest' => ['enum' => ['One'], 'enum_titles' => ['Eins']],
                ]],
            ],
            ['locale' => 'en', 'format' => 'builder', 'updatedAt' => '2026-01-01 00:00:00', 'data' => $builder],
            [
                'locale' => 'es',
                'format' => 'builder',
                'updatedAt' => '2026-01-01 00:00:00',
                'data' => ['version' => 1, 'fields' => [
                    ['id' => 'other', 'source' => 'custom', 'key' => 'dest', 'type' => 'choice', 'choices' => [['value' => 'One', 'label' => ['_' => 'Uno']]], 'label' => ['_' => 'Destino', 'fr' => 'Destination FR']],
                ]],
            ],
        ]);

        $this->assertSame([], $result['warnings']);
        $form = $result['form'];
        $this->assertValid($form);
        $this->assertSame('ws', $form['workspaceId']);
        $this->assertSame(['f_section', 'f_title', 'f_dest'], array_column($form['fields'], 'id'));
        $this->assertSame(['_' => 'Info'], $form['fields'][0]['label']);
        $this->assertSame(['_' => 'Title', 'fr' => 'Titre', 'de' => 'Titel'], $form['fields'][1]['label']);
        $this->assertSame(['de' => 'Hilfe'], $form['fields'][1]['help']);
        $this->assertSame(['_' => 'Destination', 'es' => 'Destino', 'fr' => 'Destination FR'], $form['fields'][2]['label']);
        $this->assertSame(['_' => 'One', 'es' => 'Uno', 'de' => 'Eins'], $form['fields'][2]['choices'][0]['label']);
    }

    public function testAnUnresolvableSchemaGivesNoForm(): void
    {
        $result = Version20261111120000::mergeSchemas([
            ['locale' => null, 'format' => 'liform', 'data' => ['properties' => ['a' => ['$ref' => 'http://example.com/schema.json']]]],
        ]);

        $this->assertNull($result['form']);
        $this->assertCount(1, $result['warnings']);
    }

    private function assertValid(array $definition): void
    {
        // What the database gives back
        $definition = json_decode(json_encode($definition, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(FormDefinition::class, FormDefinition::fromArray($definition));
    }

    private static function withoutIds(array $fields): array
    {
        return array_map(static function (array $field): array {
            unset($field['id']);

            return $field;
        }, $fields);
    }
}
