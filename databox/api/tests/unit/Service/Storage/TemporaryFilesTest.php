<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Storage;

use App\Service\Storage\TemporaryFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TemporaryFilesTest extends TestCase
{
    private const int DAY = 86400;

    private string $root;
    private string $dir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/'.uniqid('temporary-files-test', true);
        $this->dir = $this->root.'/nested/tmp';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testDirectoryIsCreatedOnDemand(): void
    {
        $temporaryFiles = new TemporaryFiles($this->dir);
        $this->assertDirectoryDoesNotExist($this->dir);

        $this->assertSame($this->dir, $temporaryFiles->getDirectory());
        $this->assertDirectoryExists($this->dir);
    }

    public function testCreatePath(): void
    {
        $temporaryFiles = new TemporaryFiles($this->dir);

        $path = $temporaryFiles->createPath('download-file');
        $this->assertSame($this->dir, dirname($path));
        $this->assertStringStartsWith('download-file', basename($path));
        $this->assertFileDoesNotExist($path);

        $withExtension = $temporaryFiles->createPath('archive-file', 'zip');
        $this->assertSame($this->dir, dirname($withExtension));
        $this->assertStringStartsWith('archive-file', basename($withExtension));
        $this->assertStringEndsWith('.zip', $withExtension);

        $this->assertNotSame($path, $temporaryFiles->createPath('download-file'));
    }

    public function testPurgeOfMissingDirectory(): void
    {
        $this->assertSame(0, (new TemporaryFiles($this->dir))->purge(self::DAY));
        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function testPurge(): void
    {
        $temporaryFiles = new TemporaryFiles($this->dir);
        $dir = $temporaryFiles->getDirectory();
        $old = time() - 2 * self::DAY;

        $oldFile = $this->createFile($dir.'/old', $old);
        $recentFile = $this->createFile($dir.'/recent');

        // Old directory holding old files only: emptied, then removed
        $oldDir = $dir.'/2026-01-01';
        $this->createFile($oldDir.'/a/file', $old);
        touch($oldDir.'/a', $old);
        $this->createFile($oldDir.'/file', $old);
        touch($oldDir, $old);

        // Old directory holding a recent file: kept
        $keptDir = $dir.'/cache';
        $this->createFile($keptDir.'/old', $old);
        $keptFile = $this->createFile($keptDir.'/recent');
        touch($keptDir, $old);

        // Recent empty directory: kept
        $recentDir = $dir.'/working';
        mkdir($recentDir);

        $this->assertSame(6, $temporaryFiles->purge(self::DAY));

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);
        $this->assertDirectoryDoesNotExist($oldDir);
        $this->assertFileDoesNotExist($keptDir.'/old');
        $this->assertFileExists($keptFile);
        $this->assertDirectoryExists($recentDir);
        $this->assertDirectoryExists($dir);
    }

    public function testSymbolicLinksAreNotFollowed(): void
    {
        $temporaryFiles = new TemporaryFiles($this->dir);
        $dir = $temporaryFiles->getDirectory();

        $outside = $this->root.'/outside';
        $outsideFile = $this->createFile($outside.'/file', time() - 2 * self::DAY);
        touch($outside, time() - 2 * self::DAY);
        symlink($outside, $dir.'/link');
        // PHP cannot change the mtime of a link itself
        exec(sprintf('touch -h -d @%d %s 2>/dev/null', time() - 2 * self::DAY, escapeshellarg($dir.'/link')), result_code: $code);
        if (0 !== $code) {
            $this->markTestSkipped('touch -h is not available');
        }

        $this->assertSame(1, $temporaryFiles->purge(self::DAY));
        $this->assertFalse(is_link($dir.'/link'));
        $this->assertFileExists($outsideFile);
    }

    private function createFile(string $path, ?int $mtime = null): string
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'content');
        if (null !== $mtime) {
            touch($path, $mtime);
        }

        return $path;
    }
}
