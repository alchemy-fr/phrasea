<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * The directory of a remote command, created by the worker in the jobs directory of the shared
 * volume, handed to the uid of the job while its tool runs, then given back to the worker.
 */
final readonly class JobDirectory
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

    private function __construct(public string $path)
    {
    }

    /**
     * @param int $ownerUid the uid of the worker, which must own the directory
     *
     * @throws RemoteExecException
     */
    public static function open(string $path, string $jobsDir, int $ownerUid): self
    {
        $name = basename($path);
        if (dirname($path) !== $jobsDir || !preg_match(self::NAME_PATTERN, $name)) {
            throw new RemoteExecException(sprintf('The job directory must be a direct child of %s', $jobsDir));
        }

        clearstatcache();
        $stat = @lstat($path);
        if (false === $stat || !FileTree::isDirectoryMode($stat['mode'])) {
            throw new RemoteExecException('The job directory does not exist or is not a directory');
        }
        if ($stat['uid'] !== $ownerUid) {
            throw new RemoteExecException('The job directory is not owned by the worker');
        }

        return new self($path);
    }

    /**
     * Gives the whole tree to $uid:$gid (without following any link) and makes the directory
     * private to it.
     */
    public function chown(int $uid, int $gid): void
    {
        FileTree::walk($this->path, static function (string $path, array $stat) use ($uid, $gid): void {
            self::chownEntry($path, $stat, $uid, $gid);
        });
        $stat = @lstat($this->path);
        if (false === $stat) {
            throw new RemoteExecException('The job directory vanished');
        }
        self::chownEntry($this->path, $stat, $uid, $gid);
        if (!@chmod($this->path, 0700)) {
            throw new RemoteExecException('Cannot restrict the job directory');
        }
    }

    /**
     * Removes everything that is neither a regular file nor a directory: symbolic links (to
     * /proc/self/environ, to a file of another job…), fifos, sockets, devices. The worker then
     * only finds plain files that the job wrote itself.
     */
    public function removeSpecialFiles(): void
    {
        FileTree::walk($this->path, static function (string $path, array $stat): void {
            if (FileTree::isDirectoryMode($stat['mode']) || FileTree::isRegularFileMode($stat['mode'])) {
                return;
            }
            if (!@unlink($path)) {
                throw new RemoteExecException(sprintf('Cannot remove "%s"', $path));
            }
        });
    }

    public function remove(): void
    {
        FileTree::remove($this->path);
    }

    private static function chownEntry(string $path, array $stat, int $uid, int $gid): void
    {
        $isSpecial = !FileTree::isDirectoryMode($stat['mode']) && !FileTree::isRegularFileMode($stat['mode']);
        if ($stat['uid'] !== $uid && !@lchown($path, $uid)) {
            throw new RemoteExecException(sprintf('Cannot change the owner of "%s"', $path));
        }
        if ($stat['gid'] !== $gid && !@lchgrp($path, $gid)) {
            throw new RemoteExecException(sprintf('Cannot change the group of "%s"', $path));
        }
        // No set-user-ID, set-group-ID or sticky bit survives a change of owner
        if (!$isSpecial && 0 !== ($stat['mode'] & 07000) && !@chmod($path, $stat['mode'] & 0777)) {
            throw new RemoteExecException(sprintf('Cannot change the mode of "%s"', $path));
        }
    }
}
