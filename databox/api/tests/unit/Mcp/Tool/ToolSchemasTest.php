<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Mcp\Tool\ToolSchemas;
use App\Model\AssetTypeEnum;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shared schemas are hand-written constants: make sure they are valid JSON Schemas
 * that accept what the API accepts.
 */
class ToolSchemasTest extends TestCase
{
    #[DataProvider('getTranslations')]
    public function testTranslationsSchema(mixed $translations, bool $valid): void
    {
        $this->assertSame($valid, $this->isValid(ToolSchemas::TRANSLATIONS, $translations));
    }

    public static function getTranslations(): array
    {
        return [
            'null' => [null, true],
            'by field then locale' => [['name' => ['fr' => 'Nom', 'de' => 'Name']], true],
            'field not an object' => [['name' => 'Nom'], false],
            'value not a string' => [['name' => ['fr' => 42]], false],
        ];
    }

    #[DataProvider('getAttributes')]
    public function testAttributesSchema(mixed $attributes, bool $valid): void
    {
        $this->assertSame($valid, $this->isValid(ToolSchemas::ATTRIBUTES, $attributes));
    }

    public static function getAttributes(): array
    {
        return [
            'null' => [null, true],
            'values of any type' => [[
                ['definitionId' => 'd1', 'value' => 'Sunset'],
                ['definitionId' => 'd2', 'value' => 42, 'locale' => 'fr', 'position' => 1],
                ['definitionId' => 'd3', 'value' => ['lat' => 48.85, 'lng' => 2.35]],
            ], true],
            'missing definition' => [[['value' => 'Sunset']], false],
            'missing value' => [[['definitionId' => 'd1']], false],
        ];
    }

    #[DataProvider('getAttributeActions')]
    public function testAttributeActionsSchema(mixed $actions, bool $valid): void
    {
        $this->assertSame($valid, $this->isValid(ToolSchemas::ATTRIBUTE_ACTIONS, $actions));
    }

    public static function getAttributeActions(): array
    {
        return [
            'set, add, delete' => [[
                ['action' => 'set', 'definitionId' => 'd1', 'value' => 'Sunset', 'confidence' => 0.8],
                ['action' => 'set', 'definitionId' => 'd2', 'value' => ['a', 'b']],
                ['action' => 'add', 'definitionId' => 'd2', 'value' => 'c', 'locale' => 'en'],
                ['action' => 'delete', 'definitionId' => 'd3', 'id' => 'x'],
            ], true],
            'empty' => [[], false],
            'unknown action' => [[['action' => 'replace', 'definitionId' => 'd1']], false],
            'missing action' => [[['definitionId' => 'd1', 'value' => 'x']], false],
            'confidence out of range' => [[['action' => 'set', 'definitionId' => 'd1', 'value' => 'x', 'confidence' => 2]], false],
        ];
    }

    public function testPrivacyMatchesTheApi(): void
    {
        $this->assertEqualsCanonicalizing(
            [null, ...array_values(WorkspaceItemPrivacyInterface::KEYS)],
            ToolSchemas::PRIVACY['enum'],
        );
    }

    public function testTargetMatchesTheApi(): void
    {
        $this->assertEqualsCanonicalizing(
            [null, ...array_map(static fn (AssetTypeEnum $type): int => $type->value, AssetTypeEnum::cases())],
            ToolSchemas::TARGET['enum'],
        );
    }

    private function isValid(array $schema, mixed $data): bool
    {
        return (new Validator())
            ->validate(Helper::toJSON($data), Helper::toJSON($schema))
            ->isValid();
    }
}
