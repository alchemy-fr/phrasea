<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The directory of the temporary files of the databox processes (downloads, exports, clips,
 * rendition working files…). Each caller deletes its files once used; the workers purge on
 * start the entries left by a process killed in the middle of a message (OOM, timeout, deployment).
 *
 * The purge also removes the remote file cache of rendition-factory: a cached file removed when
 * a worker starts while another one reads it is downloaded again at its next use.
 */
readonly class TemporaryFiles
{
    public function __construct(
        #[Autowire(param: 'app.tmp_dir')]
        private string $directory,
    ) {
    }

    public function getDirectory(): string
    {
        // Several processes may create it at once
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory', $this->directory));
        }

        return $this->directory;
    }

    /**
     * A unique path in the directory: nothing is created there (a caller needing a directory creates it).
     */
    public function createPath(string $prefix, ?string $extension = null): string
    {
        return $this->getDirectory().'/'.uniqid($prefix, true).(null !== $extension ? '.'.$extension : '');
    }

    /**
     * Deletes the files modified more than $maxAge seconds ago, then the directories left empty
     * and not modified for as long. Symbolic links are removed, never followed.
     *
     * @return int the number of deleted entries
     */
    public function purge(int $maxAge): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        return $this->purgeDirectory($this->directory, time() - $maxAge);
    }

    private function purgeDirectory(string $directory, int $threshold): int
    {
        $entries = @scandir($directory);
        if (false === $entries) {
            return 0;
        }

        $deleted = 0;
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $directory.'/'.$entry;
            // The entries vanish when other workers purge at the same time
            if (is_link($path)) {
                if (self::isOlderThan($path, $threshold, true) && @unlink($path)) {
                    ++$deleted;
                }
            } elseif (is_dir($path)) {
                // Read before its content is purged, which touches it
                $isOld = self::isOlderThan($path, $threshold);
                $deleted += $this->purgeDirectory($path, $threshold);
                if ($isOld && self::isEmptyDirectory($path) && @rmdir($path)) {
                    ++$deleted;
                }
            } elseif (self::isOlderThan($path, $threshold) && @unlink($path)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    private static function isOlderThan(string $path, int $threshold, bool $link = false): bool
    {
        $stat = $link ? @lstat($path) : @stat($path);

        return false !== $stat && $stat['mtime'] < $threshold;
    }

    private static function isEmptyDirectory(string $path): bool
    {
        $entries = @scandir($path);

        return false !== $entries && [] === array_diff($entries, ['.', '..']);
    }
}
