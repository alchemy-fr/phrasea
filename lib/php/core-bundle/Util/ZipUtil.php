<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

final class ZipUtil
{
    /**
     * Archives the whole content of $sourceDir into $archivePath (overwritten if it exists).
     * Entries are stored relative to $sourceDir, under $rootDir when given.
     */
    public static function zipDirectory(string $sourceDir, string $archivePath, ?string $rootDir = null): void
    {
        if (!is_dir($sourceDir)) {
            throw new \RuntimeException(sprintf('The directory "%s" does not exist.', $sourceDir));
        }

        $zip = new \ZipArchive();
        $result = $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException(sprintf('Cannot create the archive "%s" (error code %d).', $archivePath, $result));
        }

        $prefix = null !== $rootDir && '' !== $rootDir ? trim($rootDir, '/').'/' : '';
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $sourceDirLength = strlen($sourceDir) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $entries = 0;
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $entryName = $prefix.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $sourceDirLength));
            if ($file->isDir()) {
                $zip->addEmptyDir($entryName);
            } else {
                $zip->addFile($file->getPathname(), $entryName);
            }
            ++$entries;
        }

        if (0 === $entries) {
            // ZipArchive writes no file at all for an archive without entries.
            $zip->close();
            throw new \RuntimeException(sprintf('Cannot create the archive "%s": the directory "%s" is empty.', $archivePath, $sourceDir));
        }

        if (!$zip->close()) {
            throw new \RuntimeException(sprintf('Cannot write the archive "%s": %s', $archivePath, $zip->getStatusString()));
        }
    }
}
