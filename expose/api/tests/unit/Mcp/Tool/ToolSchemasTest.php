<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use App\Mcp\Tool\ToolSchemas;
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
    #[DataProvider('getConfigs')]
    public function testConfigSchema(mixed $config, bool $valid): void
    {
        $this->assertSame($valid, $this->isValid(ToolSchemas::CONFIG, $config));
    }

    public static function getConfigs(): array
    {
        return [
            'null' => [null, true],
            'empty' => [new \stdClass(), true],
            'full' => [[
                'enabled' => true,
                'publiclyListed' => false,
                'layout' => 'mapbox',
                'theme' => 'dark',
                'css' => 'body {}',
                'downloadEnabled' => true,
                'downloadViaEmail' => false,
                'includeDownloadTermsInZippy' => true,
                'beginsAt' => '2026-01-01T00:00:00+00:00',
                'expiresAt' => '2027-01-01T00:00:00+00:00',
                'securityMethod' => 'password',
                'securityOptions' => ['password' => 's3cret'],
                'terms' => ['enabled' => true, 'text' => '<p>Terms</p>', 'url' => 'https://example.com'],
                'downloadTerms' => ['enabled' => false],
                'mapOptions' => ['lat' => 48.85, 'lng' => 2.35, 'zoom' => 5, 'mapLayout' => 'streets'],
                'layoutOptions' => ['displayMap' => true, 'displayMapPins' => false, 'logoUrl' => 'https://example.com/logo.png'],
            ], true],
            'public security' => [['securityMethod' => null], true],
            'unknown layout' => [['layout' => 'carousel'], false],
            'unknown security method' => [['securityMethod' => 'ldap'], false],
            'wrong type' => [['enabled' => 'yes'], false],
            'not an object' => ['grid', false],
        ];
    }

    #[DataProvider('getTranslations')]
    public function testTranslationsSchema(mixed $translations, bool $valid): void
    {
        $this->assertSame($valid, $this->isValid(ToolSchemas::TRANSLATIONS, $translations));
    }

    public static function getTranslations(): array
    {
        return [
            'null' => [null, true],
            'by locale' => [['fr' => ['title' => 'Titre', 'description' => 'Desc'], 'de' => ['title' => 'Titel']], true],
            'locale not an object' => [['fr' => 'Titre'], false],
            'title not a string' => [['fr' => ['title' => 42]], false],
        ];
    }

    public function testLayoutsMatchTheClient(): void
    {
        // Keep in sync with LayoutEnum in expose/client/src/types.ts
        $this->assertEqualsCanonicalizing(
            ['gallery', 'grid', 'download', 'mapbox'],
            ToolSchemas::CONFIG['properties']['layout']['enum'],
        );
    }

    private function isValid(array $schema, mixed $data): bool
    {
        return (new Validator())
            ->validate(Helper::toJSON($data), Helper::toJSON($schema))
            ->isValid();
    }
}
