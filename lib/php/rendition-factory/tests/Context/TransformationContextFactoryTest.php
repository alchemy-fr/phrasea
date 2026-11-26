<?php

namespace Alchemy\RenditionFactory\Tests\Context;

use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use PHPUnit\Framework\TestCase;

class TransformationContextFactoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/'.uniqid('rendition-factory-test', true);
    }

    protected function tearDown(): void
    {
        self::remove($this->root);
    }

    public function testMissingWorkingDirectoryIsCreated(): void
    {
        $base = $this->root.'/nested/tmp';
        $context = new TransformationContextFactory(new MimeTypeGuesser(), $base)->create();

        $workingDirectory = $context->getWorkingDirectory();
        $this->assertDirectoryExists($workingDirectory);
        $this->assertSame($base.'/'.date('Y-m-d'), dirname($workingDirectory));
        $this->assertDirectoryExists($base.'/cache');

        // The cache may be removed in the meantime
        self::remove($base.'/cache');
        $this->assertDirectoryExists($context->getCacheDir('remote'));
        $this->assertSame($base.'/cache/remote', $context->getCacheDir('remote'));
    }

    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($dir);
    }
}
