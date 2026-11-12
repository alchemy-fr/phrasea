<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InlineContentTypePolicyTest extends TestCase
{
    #[DataProvider('getTypes')]
    public function testCanBeDisplayedInline(?string $type, bool $expected): void
    {
        $this->assertSame($expected, InlineContentTypePolicy::canBeDisplayedInline($type));
    }

    public static function getTypes(): array
    {
        return [
            ['image/jpeg', true],
            ['Image/PNG; foo=bar', true],
            ['video/mp4', true],
            ['audio/mpeg', true],
            ['application/pdf', true],
            ['image/svg+xml', false],
            ['IMAGE/SVG+XML', false],
            ['text/html', false],
            ['application/xhtml+xml', false],
            ['text/xml', false],
            ['application/xml', false],
            ['text/plain', false],
            ['application/octet-stream', false],
            ['application/javascript', false],
            ['', false],
            ['image', false],
            [null, false],
        ];
    }

    #[DataProvider('getResponseHeaders')]
    public function testGetResponseHeaders(?string $type, bool $inline, string $expectedType, ?string $expectedDisposition): void
    {
        $this->assertSame([
            'contentType' => $expectedType,
            'contentDisposition' => $expectedDisposition,
        ], InlineContentTypePolicy::getResponseHeaders($type, 'foo.bin', $inline));
    }

    public static function getResponseHeaders(): array
    {
        $attachment = 'attachment; filename=foo.bin';

        return [
            'displayable, inline' => ['image/jpeg', true, 'image/jpeg', null],
            'displayable, normalized' => ['Video/MP4; codecs=avc1', true, 'video/mp4', null],
            'displayable, not inline' => ['application/pdf', false, 'application/pdf', $attachment],
            'svg, inline' => ['image/svg+xml', true, 'application/octet-stream', $attachment],
            'html, inline' => ['text/html', true, 'application/octet-stream', $attachment],
            'unknown type, inline' => [null, true, 'application/octet-stream', $attachment],
            'unknown type' => [null, false, 'application/octet-stream', $attachment],
        ];
    }
}
