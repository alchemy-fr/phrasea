<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Tests\RemoteExec;

use Alchemy\RenditionFactory\RemoteExec\FileTree;
use Alchemy\RenditionFactory\RemoteExec\JobDirectory;
use Alchemy\RenditionFactory\RemoteExec\RemoteExecException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobDirectoryTest extends TestCase
{
    private string $root;
    private string $jobs;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/'.uniqid('job-directory-test', true);
        $this->jobs = $this->root.'/jobs';
        mkdir($this->jobs, 0711, true);
    }

    protected function tearDown(): void
    {
        FileTree::remove($this->root);
    }

    public function testOpen(): void
    {
        mkdir($this->jobs.'/0123abcd');

        $this->assertSame($this->jobs.'/0123abcd', JobDirectory::open($this->jobs.'/0123abcd', $this->jobs, posix_getuid())->path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'not a direct child' => ['{jobs}/a/b'];
        yield 'outside' => ['{root}/other'];
        yield 'relative segment' => ['{jobs}/..'];
        yield 'hidden name' => ['{jobs}/.job'];
        yield 'missing' => ['{jobs}/missing'];
        yield 'symbolic link' => ['{jobs}/link'];
        yield 'file' => ['{jobs}/file'];
    }

    #[DataProvider('provideInvalidPaths')]
    public function testInvalidJobDirectoryIsRefused(string $path): void
    {
        mkdir($this->jobs.'/a/b', 0700, true);
        mkdir($this->root.'/other');
        symlink($this->root.'/other', $this->jobs.'/link');
        touch($this->jobs.'/file');

        $this->expectException(RemoteExecException::class);

        JobDirectory::open(str_replace(['{jobs}', '{root}'], [$this->jobs, $this->root], $path), $this->jobs, posix_getuid());
    }

    public function testDirectoryOfAnotherUserIsRefused(): void
    {
        mkdir($this->jobs.'/job');

        $this->expectException(RemoteExecException::class);

        JobDirectory::open($this->jobs.'/job', $this->jobs, posix_getuid() + 1);
    }

    public function testRemoveSpecialFiles(): void
    {
        $path = $this->jobs.'/job';
        mkdir($path.'/sub', 0700, true);
        file_put_contents($path.'/out.jpg', 'jpg');
        file_put_contents($path.'/sub/out.pdf', 'pdf');
        symlink('/proc/self/environ', $path.'/environ.jpg');
        symlink($this->root, $path.'/sub/root');
        posix_mkfifo($path.'/fifo', 0600);
        $server = stream_socket_server('unix://'.$path.'/sub/socket');

        try {
            JobDirectory::open($path, $this->jobs, posix_getuid())->removeSpecialFiles();
        } finally {
            fclose($server);
        }

        $this->assertSame(['out.jpg', 'sub'], array_values(array_diff(scandir($path), ['.', '..'])));
        $this->assertSame(['out.pdf'], array_values(array_diff(scandir($path.'/sub'), ['.', '..'])));
        $this->assertDirectoryExists($this->jobs);
    }

    public function testChownMakesTheDirectoryPrivateAndDropsSpecialBits(): void
    {
        $path = $this->jobs.'/job';
        mkdir($path, 0755);
        file_put_contents($path.'/tool', '#!/bin/sh');
        chmod($path.'/tool', 04755);

        JobDirectory::open($path, $this->jobs, posix_getuid())->chown(posix_getuid(), posix_getgid());
        clearstatcache();

        $this->assertSame(0700, fileperms($path) & 07777);
        $this->assertSame(0755, fileperms($path.'/tool') & 07777);
    }

    public function testRemoveNeverFollowsLinks(): void
    {
        $path = $this->jobs.'/job';
        mkdir($path);
        mkdir($this->root.'/outside');
        file_put_contents($this->root.'/outside/keep', 'keep');
        symlink($this->root.'/outside', $path.'/outside');

        JobDirectory::open($path, $this->jobs, posix_getuid())->remove();

        $this->assertDirectoryDoesNotExist($path);
        $this->assertFileExists($this->root.'/outside/keep');
    }
}
