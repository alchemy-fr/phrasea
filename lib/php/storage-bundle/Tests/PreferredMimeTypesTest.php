<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\StorageBundle\Util\PreferredMimeTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PreferredMimeTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        PreferredMimeTypes::setDefault(new PreferredMimeTypes());
    }

    /**
     * @param string[] $expectedTypes
     */
    #[DataProvider('getDefaultCases')]
    public function testDefaultPreferences(string $extension, array $expectedTypes): void
    {
        $this->assertSame($expectedTypes, new PreferredMimeTypes()->getMimeTypes($extension));
    }

    public static function getDefaultCases(): iterable
    {
        yield ['wmv', ['video/x-ms-wmv', 'audio/x-ms-wmv']];
        yield ['WMV', ['video/x-ms-wmv', 'audio/x-ms-wmv']];
        yield ['wma', ['audio/x-ms-wma', 'audio/wma']];
        yield ['jpg', ['image/jpeg', 'image/pjpeg']];
        yield ['unknownext', []];
    }

    /**
     * @param string[] $expectedTypes
     */
    #[DataProvider('getConfiguredCases')]
    public function testConfiguredPreferences(string $preferredTypes, string $extension, array $expectedTypes): void
    {
        $this->assertSame($expectedTypes, new PreferredMimeTypes($preferredTypes)->getMimeTypes($extension));
    }

    public static function getConfiguredCases(): iterable
    {
        yield 'nothing preferred' => ['', 'wmv', ['audio/x-ms-wmv', 'video/x-ms-wmv']];
        yield 'several extensions, dot and case' => ['image/pjpeg(.JPG, jpeg),video/x-ms-wmv(.wmv)', 'jpeg', ['image/pjpeg', 'image/jpeg']];
        yield 'second entry' => ['image/pjpeg(.jpg),video/x-ms-wmv(.wmv)', 'wmv', ['video/x-ms-wmv', 'audio/x-ms-wmv']];
        yield 'type unknown for the extension' => ['application/x-foo(.wmv)', 'wmv', ['application/x-foo', 'audio/x-ms-wmv', 'video/x-ms-wmv']];
        yield 'unknown extension' => ['application/x-foo(.foo)', 'foo', ['application/x-foo']];
    }

    #[DataProvider('getInvalidCases')]
    public function testInvalidPreferences(string $preferredTypes): void
    {
        $mimeTypes = new PreferredMimeTypes($preferredTypes);

        $this->expectException(\InvalidArgumentException::class);
        $mimeTypes->getMimeTypes('wmv');
    }

    public static function getInvalidCases(): iterable
    {
        yield 'wildcard' => ['video/*(.wmv)'];
        yield 'no extension' => ['video/x-ms-wmv'];
    }

    public function testExtensionsAreNotChanged(): void
    {
        $this->assertSame(['wmv'], new PreferredMimeTypes()->getExtensions('video/x-ms-wmv'));
    }

    public function testFileUtilReadsTheDefaultInstance(): void
    {
        $this->assertSame('video/x-ms-wmv', FileUtil::getTypeFromExtension('wmv'));

        PreferredMimeTypes::setDefault(new PreferredMimeTypes(''));
        $this->assertSame('audio/x-ms-wmv', FileUtil::getTypeFromExtension('wmv'));

        PreferredMimeTypes::setDefault(new PreferredMimeTypes('image/pjpeg(.jpg)'));
        $this->assertSame('image/pjpeg', FileUtil::getTypeFromExtension('jpg'));
    }
}
