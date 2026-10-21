<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Util;

use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\CoreBundle\Util\ZipUtil;
use PHPUnit\Framework\TestCase;

class ZipUtilTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('zip-util-test', true);
        mkdir($this->workDir.'/src/sub/deep', 0777, true);
    }

    protected function tearDown(): void
    {
        FilesystemUtils::rrmdir($this->workDir);
    }

    public function testZipDirectoryWithSubDirectories(): void
    {
        file_put_contents($this->workDir.'/src/a.txt', 'A');
        file_put_contents($this->workDir.'/src/sub/b.txt', 'B');
        mkdir($this->workDir.'/src/empty');

        $archive = $this->workDir.'/out.zip';
        ZipUtil::zipDirectory($this->workDir.'/src', $archive);

        $this->assertSame([
            'a.txt',
            'empty/',
            'sub/',
            'sub/b.txt',
            'sub/deep/',
        ], $this->listEntries($archive));
        $this->assertSame('B', $this->readEntry($archive, 'sub/b.txt'));
    }

    public function testZipDirectoryWithRootDir(): void
    {
        file_put_contents($this->workDir.'/src/a.txt', 'A');
        file_put_contents($this->workDir.'/src/sub/b.txt', 'B');

        $archive = $this->workDir.'/out.zip';
        ZipUtil::zipDirectory($this->workDir.'/src', $archive, 'content');

        $this->assertSame([
            'content/a.txt',
            'content/sub/',
            'content/sub/b.txt',
            'content/sub/deep/',
        ], $this->listEntries($archive));
        $this->assertSame('A', $this->readEntry($archive, 'content/a.txt'));
    }

    public function testExistingArchiveIsOverwritten(): void
    {
        file_put_contents($this->workDir.'/src/a.txt', 'A');
        $archive = $this->workDir.'/out.zip';
        file_put_contents($archive, 'not a zip');

        ZipUtil::zipDirectory($this->workDir.'/src', $archive);

        $this->assertContains('a.txt', $this->listEntries($archive));
    }

    public function testEmptyDirectoryThrows(): void
    {
        mkdir($this->workDir.'/empty');
        $archive = $this->workDir.'/out.zip';

        $this->expectException(\RuntimeException::class);
        try {
            ZipUtil::zipDirectory($this->workDir.'/empty', $archive);
        } finally {
            $this->assertFileDoesNotExist($archive);
        }
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        ZipUtil::zipDirectory($this->workDir.'/missing', $this->workDir.'/out.zip');
    }

    /**
     * @return list<string>
     */
    private function listEntries(string $archive): array
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archive));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($entries);

        return $entries;
    }

    private function readEntry(string $archive, string $name): string|false
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archive));
        $content = $zip->getFromName($name);
        $zip->close();

        return $content;
    }
}
