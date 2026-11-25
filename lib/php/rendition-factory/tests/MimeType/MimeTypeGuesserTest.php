<?php

namespace Alchemy\RenditionFactory\Tests\MimeType;

use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        yield ['/tmp/clip.wmv', 'video/x-ms-wmv'];
        yield ['/tmp/CLIP.WMV', 'video/x-ms-wmv'];
        yield ['/tmp/song.wma', 'audio/x-ms-wma'];
        yield ['/tmp/photo.jpg', 'image/jpeg'];
        yield ['/tmp/movie.mp4', 'video/mp4'];
        yield ['/tmp/noextension', null];
    }
}
