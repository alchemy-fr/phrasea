<?php

declare(strict_types=1);

namespace App\Tests\Unit\Automation;

use App\Automation\Export\ExportPathTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportPathTemplateTest extends TestCase
{
    public static function invalidTemplates(): array
    {
        return [
            'blank' => [' / '],
            'parent directory' => ['{collection}/../{assetId}'],
            'unknown variable' => ['{assetId}-{size}.{ext}'],
            'backslash' => ['{collection}\\{assetId}'],
            'control character' => ["{assetId}\n.{ext}"],
        ];
    }

    #[DataProvider('invalidTemplates')]
    public function testInvalidTemplates(string $template): void
    {
        $this->assertNotNull(ExportPathTemplate::validate($template));
    }

    public function testValidTemplates(): void
    {
        $this->assertNull(ExportPathTemplate::validate(ExportPathTemplate::DEFAULT));
        $this->assertNull(ExportPathTemplate::validate('exports/{workspace}/{date}/{title}-{rendition}.{ext}'));
        $this->assertNull(ExportPathTemplate::validate('{originalName}'));
    }

    public function testRender(): void
    {
        $this->assertSame('photos/a1-preview.jpg', ExportPathTemplate::render(ExportPathTemplate::DEFAULT, [
            'collection' => 'photos',
            'assetId' => 'a1',
            'rendition' => 'preview',
            'ext' => 'jpg',
        ]));
    }

    public function testEmptyValuesDoNotLeaveEmptySegments(): void
    {
        // No reference collection, no extension
        $this->assertSame('a1-source', ExportPathTemplate::render(ExportPathTemplate::DEFAULT, [
            'collection' => null,
            'assetId' => 'a1',
            'rendition' => 'source',
            'ext' => '',
        ]));
    }

    public function testValuesCannotAddDirectories(): void
    {
        $this->assertSame('ws/a-b-c.txt', ExportPathTemplate::render('{workspace}/{originalName}', [
            'workspace' => 'ws',
            'originalName' => 'a/b\\c.txt',
        ]));
        $this->assertSame('ws/x', ExportPathTemplate::render('{workspace}/{originalName}/x', [
            'workspace' => 'ws',
            'originalName' => '..',
        ]));
    }

    public function testRenderRefusesAnEmptyPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ExportPathTemplate::render('{collection}/{title}', []);
    }

    public function testUsedVariables(): void
    {
        $this->assertSame(['collection', 'assetId', 'rendition', 'ext'], ExportPathTemplate::getUsedVariables(ExportPathTemplate::DEFAULT));
    }
}
