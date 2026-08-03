<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Util\FileUtil;
use PHPUnit\Framework\TestCase;

class FileUtilTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testGetExtensionFromPath(?string $expectedExtension, string $path): void
    {
        $this->assertEquals($expectedExtension, FileUtil::getExtensionFromPath($path));
    }

    /**
     * @dataProvider getTypeCases
     */
    public function testGetTypeFromExtension(?string $expectedType, ?string $extension): void
    {
        $this->assertEquals($expectedType, FileUtil::getTypeFromExtension($extension));
    }

    public function getTypeCases(): array
    {
        return [
            ['image/jpeg', 'jpg'],
            ['application/x-gzip', 'gz'],
            ['application/x-compressed-tar', 'tar.gz'],
            ['application/x-xz-compressed-tar', 'tar.xz'],
            ['application/x-zstd-compressed-tar', 'tar.zst'],
            ['application/x-gzip', 'sql.gz'],
            [null, 'unknownext'],
            [null, null],
        ];
    }

    /**
     * @dataProvider getStripExtensionCases
     */
    public function testStripExtension(string $expected, string $filename): void
    {
        $this->assertEquals($expected, FileUtil::stripExtension($filename));
    }

    public function getStripExtensionCases(): array
    {
        return [
            ['foo', 'foo.jpg'],
            ['/path/to/foo', '/path/to/foo.jpeg'],
            ['foo', 'foo.tar.gz'],
            ['FOO', 'FOO.TAR.GZ'],
            ['foo', 'foo.tar.bz2'],
            ['my.backup', 'my.backup.gz'],
            ['foo', 'foo'],
            ['foo.', 'foo.'],
            ['foo.thisistoolong', 'foo.thisistoolong'],
            ['foo.jpg?token=abc', 'foo.jpg?token=abc'],
        ];
    }

    public function getCases(): array
    {
        return [
            ['jpg', 'foo.jpg'],
            ['jpeg', '/path/to/foo.jpeg'],
            ['jpg', 'https://foo.bar/baz.jpg?token=secret.value'],
            [null, 'foo'],
            [null, '/path/to/foo'],
            [null, 'foo.'],
            [null, 'foo.thisistoolong'],
            [null, 'foo.tar.gz.thisistoolong'],
            ['tar.gz', 'foo.tar.gz'],
            ['tar.gz', '/path/to/foo.tar.gz'],
            ['tar.gz', 'https://foo.bar/baz.tar.gz?token=secret.value'],
            ['tar.gz', 'FOO.TAR.GZ'],
            ['tar.bz2', 'foo.tar.bz2'],
            ['tar.xz', 'foo.tar.xz'],
            ['tar.zst', 'foo.tar.zst'],
            ['tar.lz', 'foo.tar.lz'],
            ['tar.lzma', 'foo.tar.lzma'],
            ['tar.br', 'foo.tar.br'],
            ['gz', 'foo.gz'],
            ['gz', 'my.backup.gz'],
            ['tgz', 'foo.tgz'],
            ['gz', '.tar.gz'],
        ];
    }
}
