<?php

declare(strict_types=1);

namespace App\Tests\Border\FileAnalyzer;

use App\Border\FileAnalyzer\Analyzer\ImageColorspaceAnalyzer;
use PHPUnit\Framework\TestCase;

class ImageColorspaceAnalyzerTest extends TestCase
{
    /**
     * @dataProvider validColorspacesProvider
     */
    public function testValidateConfigurationAcceptsDocumentedColorspaceNames(string $colorspace): void
    {
        (new ImageColorspaceAnalyzer())->validateConfiguration([
            'allowed_colorspaces' => [$colorspace],
        ]);
        (new ImageColorspaceAnalyzer())->validateConfiguration([
            'disallowed_colorspaces' => [strtoupper($colorspace)],
        ]);

        $this->expectNotToPerformAssertions();
    }

    public static function validColorspacesProvider(): iterable
    {
        foreach (['rgb', 'srgb', 'cmyk', 'grayscale', 'ycbcr', 'ycck', 'indexed', 'cielab', 'unknown'] as $cs) {
            yield $cs => [$cs];
        }
    }

    /**
     * @dataProvider invalidColorspacesProvider
     */
    public function testValidateConfigurationRejectsUnknownColorspaces(mixed $colorspace): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unknown colorspace "%s". Valid options are: rgb, srgb, cmyk, grayscale, ycbcr, ycck, indexed, cielab, unknown', $colorspace));

        (new ImageColorspaceAnalyzer())->validateConfiguration([
            'allowed_colorspaces' => [$colorspace],
        ]);
    }

    public static function invalidColorspacesProvider(): iterable
    {
        yield 'numeric index as int' => [0];
        yield 'numeric index as string' => ['3'];
        yield 'unknown name' => ['hsl'];
    }

    public function testValidateConfigurationRejectsConflictingLists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Colorspaces cannot be in both allowed and disallowed lists: rgb');

        (new ImageColorspaceAnalyzer())->validateConfiguration([
            'allowed_colorspaces' => ['rgb', 'cmyk'],
            'disallowed_colorspaces' => ['RGB'],
        ]);
    }
}
