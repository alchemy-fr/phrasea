<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Builder;

use App\Form\Builder\FieldSource;
use App\Form\Builder\FieldType;
use App\Form\Builder\FormDefinition;
use App\Form\Builder\InvalidFormDefinitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormDefinitionTest extends TestCase
{
    public function testFromArray(): void
    {
        $definition = FormDefinition::fromArray([
            'version' => 1,
            'workspaceId' => 'ws',
            'fields' => [
                ['id' => 'l', 'source' => 'layout', 'kind' => 'text', 'label' => ['en' => 'Hello']],
                ['id' => 't', 'source' => 'builtin', 'key' => 'databox_tags', 'type' => 'text'],
                ['id' => 'c', 'source' => 'custom', 'key' => 'dest', 'type' => 'choice', 'choices' => [['value' => 'a'], ['value' => 'b']], 'label' => ['fr' => 'Destination']],
                ['id' => 'k', 'source' => 'attribute', 'key' => 'title:fr', 'type' => 'text', 'attribute' => ['slug' => 'title']],
            ],
        ]);

        $this->assertSame('ws', $definition->workspaceId);
        $this->assertCount(4, $definition->fields);
        $this->assertCount(3, $definition->getInputFields());

        $tags = $definition->getField('databox_tags');
        $this->assertSame(FieldSource::Builtin, $tags?->source);
        $this->assertSame(FieldType::Tag, $tags->type, 'A built-in field has its own type');
        $this->assertTrue($tags->multiple);

        $choice = $definition->getField('dest');
        $this->assertSame(['a', 'b'], $choice?->choices);
        $this->assertSame('Destination', $choice->getLabel('en'));
        $this->assertNull($definition->getField('unknown'));
    }

    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitions(array $data, string $path): void
    {
        try {
            FormDefinition::fromArray($data);
            $this->fail('The definition should be invalid');
        } catch (InvalidFormDefinitionException $e) {
            $this->assertSame($path, $e->path, $e->getMessage());
        }
    }

    public static function invalidDefinitions(): array
    {
        $def = static fn (array ...$fields): array => ['version' => 1, 'workspaceId' => 'ws', 'fields' => $fields];

        return [
            'version' => [['version' => 2, 'fields' => []], 'version'],
            'fields' => [['version' => 1, 'fields' => ['a' => []]], 'fields'],
            'no id' => [$def(['source' => 'custom', 'key' => 'a', 'type' => 'text']), 'fields.0.id'],
            'source' => [$def(['id' => 'a', 'source' => 'nope']), 'fields.0.source'],
            'key' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a b', 'type' => 'text']), 'fields.0.key'],
            'type' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'entity']), 'fields.0.type'],
            'builtin' => [$def(['id' => 'a', 'source' => 'builtin', 'key' => 'databox_nope']), 'fields.0.key'],
            'custom builtin key' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'databox_tags', 'type' => 'text']), 'fields.0.key'],
            'entity list' => [$def(['id' => 'a', 'source' => 'attribute', 'key' => 'a', 'type' => 'entity', 'attribute' => []]), 'fields.0.attribute.entityListId'],
            'choices' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'choice', 'choices' => []]), 'fields.0.choices'],
            'duplicate choice' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'choice', 'choices' => [['value' => 'x'], ['value' => 'x']]]), 'fields.0.choices.1.value'],
            'multiple boolean' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'boolean', 'multiple' => true]), 'fields.0.multiple'],
            'widget' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'text', 'widget' => 'nope']), 'fields.0.widget'],
            'hidden required' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'text', 'hidden' => true, 'required' => true]), 'fields.0.defaultValue'],
            'constraint' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'a', 'type' => 'text', 'constraints' => ['maxLength' => 'x']]), 'fields.0.constraints.maxLength'],
            'duplicate id' => [$def(['id' => 'a', 'source' => 'layout'], ['id' => 'a', 'source' => 'layout']), 'fields.1.id'],
            'duplicate key' => [$def(['id' => 'a', 'source' => 'custom', 'key' => 'k', 'type' => 'text'], ['id' => 'b', 'source' => 'custom', 'key' => 'k', 'type' => 'text']), 'fields.1.key'],
            'no workspace' => [['version' => 1, 'fields' => [['id' => 'a', 'source' => 'attribute', 'key' => 'a', 'type' => 'text', 'attribute' => []]]], 'workspaceId'],
        ];
    }
}
