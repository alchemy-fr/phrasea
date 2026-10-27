<?php

declare(strict_types=1);

namespace App\Tests\Rendition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class PreviewRawConfigTest extends TestCase
{
    public function testRawPreviewsAreExtractedBeforeOrientationAndResizing(): void
    {
        $config = Yaml::parseFile(__DIR__.'/../../src/Service/Workspace/renditions/preview.yaml');
        $transformations = $config['image']['transformations'];

        self::assertSame('extract_embedded_preview', $transformations[0]['module']);
        foreach (['image/x-adobe-dng', 'image/x-canon-cr2', 'image/x-sony-arw', 'image/x-panasonic-rw2'] as $mimeType) {
            self::assertContains($mimeType, $transformations[0]['options']['eligible_types']);
        }
        self::assertNotContains('image/x-panasonic-raw2', $transformations[0]['options']['eligible_types']);

        self::assertSame('imagine', $transformations[1]['module']);
        self::assertSame(
            ['auto_rotate', 'background_fill', 'thumbnail'],
            array_keys($transformations[1]['options']['filters'])
        );
    }
}
