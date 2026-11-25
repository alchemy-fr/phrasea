<?php

namespace Alchemy\RenditionFactory\Tests\MimeType;

use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypes;

class MimeTypeGuesserTest extends TestCase
{
    #[DataProvider('getPaths')]
    public function testGuessMimeTypeFromPath(string $path, ?string $expected): void
    {
        $this->assertSame($expected, new MimeTypeGuesser()->guessMimeTypeFromPath($path));
    }

    /**
     * @return iterable<array{string, ?string}>
     */
    public static function getPaths(): iterable
    {
        yield ['/tmp/song.wma', 'audio/x-ms-wma'];
        yield ['/tmp/photo.jpg', 'image/jpeg'];
        yield ['/tmp/PHOTO.JPG', 'image/jpeg'];
        yield ['/tmp/image.png', 'image/png'];
        yield ['/tmp/noextension', null];
    }

    public function testGuessMimeTypeFromPathTakesTheFirstTypeOfTheMimeTypes(): void
    {
        $guesser = new MimeTypeGuesser(new MimeTypes(['video/x-ms-wmv' => ['wmv']]));

        $this->assertSame('video/x-ms-wmv', $guesser->guessMimeTypeFromPath('/tmp/CLIP.WMV'));
    }
}
