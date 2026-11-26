<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use DoctrineMigrations\Version20261126203500;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The migrations are not autoloaded
require_once __DIR__.'/../../../migrations/Version20261126203500.php';

class LegacyDisplayPreferencesMigrationTest extends TestCase
{
    /**
     * @param array<mixed>      $data
     * @param array<mixed>|null $expected
     */
    #[DataProvider('getCases')]
    public function testConvertPreferences(array $data, ?array $expected): void
    {
        $this->assertSame($expected, Version20261126203500::convertPreferences($data));
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>|null}>
     */
    public static function getCases(): iterable
    {
        $legacyDisplay = [
            'thumbSize' => 200,
            'displayTitle' => true,
            'layout' => 'g',
            'displayPreview' => true,
            'previewOptions' => [
                'sizeRatio' => 60,
                'attributesRatio' => 30,
                'displayFile' => true,
            ],
        ];
        $convertedDisplay = [
            'thumbSize' => 200,
            'displayTitle' => true,
            'layout' => 'grid',
            'displayPreview' => true,
            'previewOptions' => [
                'sizeRatio' => 0.6,
                'attributesRatio' => 0.3,
                'displayFile' => true,
            ],
        ];

        yield 'legacy display and batch edit display' => [
            [
                'theme' => 'dark',
                'display' => $legacyDisplay,
                'facets' => ['a' => ['open' => true]],
                'displayBatchEdit' => $legacyDisplay,
            ],
            [
                'theme' => 'dark',
                'display' => $convertedDisplay,
                'facets' => ['a' => ['open' => true]],
                'displayBatchEdit' => $convertedDisplay,
            ],
        ];

        yield 'top-level list layout' => [
            ['layout' => 'l', 'theme' => 'light'],
            ['layout' => 'list', 'theme' => 'light'],
        ];

        yield 'numeric string ratio' => [
            ['display' => ['previewOptions' => ['sizeRatio' => '45']]],
            ['display' => ['previewOptions' => ['sizeRatio' => 0.45]]],
        ];

        yield 'already converted' => [
            [
                'layout' => 'masonry',
                'display' => [
                    'layout' => 'masonry',
                    'previewOptions' => ['sizeRatio' => 0.5, 'attributesRatio' => 0.4],
                ],
            ],
            null,
        ];

        yield 'out of range legacy values are clamped' => [
            ['display' => ['previewOptions' => ['sizeRatio' => 100, 'attributesRatio' => 5]]],
            ['display' => ['previewOptions' => ['sizeRatio' => 0.8, 'attributesRatio' => 0.2]]],
        ];

        yield 'display not an array' => [
            ['display' => 'foo', 'displayBatchEdit' => null],
            null,
        ];

        yield 'no preview options' => [
            ['display' => ['thumbSize' => 200, 'previewOptions' => null]],
            null,
        ];

        yield 'non numeric ratio' => [
            ['display' => ['previewOptions' => ['sizeRatio' => 'big', 'attributesRatio' => null]]],
            null,
        ];

        yield 'empty data' => [
            [],
            null,
        ];
    }
}
