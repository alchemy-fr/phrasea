<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * File tree operations that never follow a symbolic link: the trees they walk were written by
 * the tools, which may have planted links to anything.
 */
final class FileTree
{
    private const int S_IFMT = 0170000;
    private const int S_IFDIR = 0040000;
    private const int S_IFREG = 0100000;

    public static function isRealDirectory(string $path): bool
    {
        $stat = @lstat($path);

        return false !== $stat && self::S_IFDIR === ($stat['mode'] & self::S_IFMT);
    }

    public static function isRegularFile(string $path): bool
    {
        $stat = @lstat($path);

        return false !== $stat && self::S_IFREG === ($stat['mode'] & self::S_IFMT);
    }

    /**
     * Calls $callback for every entry below $dir (children before their directory), with the
     * lstat() of the entry. The callback may remove the entry it is given.
     *
     * @param callable(string, array<string|int, int>): void $callback
     */
    public static function walk(string $dir, callable $callback): void
    {
        $entries = @scandir($dir);
        if (false === $entries) {
            throw new RemoteExecException(sprintf('Cannot read directory "%s"', $dir));
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir.'/'.$entry;
            $stat = @lstat($path);
            if (false === $stat) {
                continue;
            }
            if (self::S_IFDIR === ($stat['mode'] & self::S_IFMT)) {
                self::walk($path, $callback);
            }
            $callback($path, $stat);
        }
    }

    public static function isDirectoryMode(int $mode): bool
    {
        return self::S_IFDIR === ($mode & self::S_IFMT);
    }

    public static function isRegularFileMode(int $mode): bool
    {
        return self::S_IFREG === ($mode & self::S_IFMT);
    }

    /**
     * Removes a tree (a link is removed, never followed).
     */
    public static function remove(string $path): void
    {
        if (self::isRealDirectory($path)) {
            self::walk($path, static function (string $entry, array $stat): void {
                if (self::isDirectoryMode($stat['mode'])) {
                    @rmdir($entry);
                } else {
                    @unlink($entry);
                }
            });
            @rmdir($path);
        } elseif (false !== @lstat($path)) {
            @unlink($path);
        }
    }
}
