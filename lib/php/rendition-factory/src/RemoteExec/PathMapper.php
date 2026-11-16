<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * Hands the files of a command line to a job directory, on the volume shared by the worker and
 * the conversion container, and brings back what the tool wrote.
 *
 * The worker directory (TMPDIR of the worker) is private to the worker: each directory of it
 * that the command refers to gets a directory of its own in the job, where:
 *
 * - an existing file given as argument is hard linked (no copy; a change in place, such as
 *   `exiftool -overwrite_original_in_place`, keeps the inode and needs nothing more);
 * - a file that does not exist yet (an output) keeps its name, in the job directory of its
 *   parent;
 * - an existing directory given as argument (LibreOffice profile, `--outdir`) is mirrored, its
 *   files hard linked.
 *
 * After the run, syncBack() renames into the worker directory every file of these job
 * directories that is not the inode linked in: new files (outputs, `-passlogfile` statistics,
 * `exiftool -w` previews) and replaced ones.
 *
 * Covered forms: the exact path, a `file://` URL (LibreOffice `-env:UserInstallation=`),
 * `key=/path`, the paths embedded in a filter (`movie=` of the video watermark), the
 * `-passlogfile` prefix and the list of the concat demuxer of ffmpeg. Anything else that refers
 * to the worker directory makes the command fail (UnmappablePathException), so that a case left
 * out shows up in tests rather than as an obscure conversion error.
 */
final class PathMapper
{
    /** A path embedded in a longer argument ends at the first character that delimits filter options */
    private const string EMBEDDED_PATH_CHARS = '[^\s\'"\[\];,:=|\\\\]';
    /** Absolute paths of the worker that a command may name, but which are not files of the worker directory */
    private const array ALLOWED_OUTSIDE_PATHS = ['/dev/null'];
    private const int MAX_CONCAT_LIST_SIZE = 1024 * 1024;

    private readonly string $workerDir;
    private readonly string $jobDir;
    /** The working directory of the command, when in the worker directory: relative paths are resolved there */
    private readonly ?string $cwd;

    /**
     * @var array<string, array{job: string, full: bool, links: array<string, int>}> keyed by the worker directory
     */
    private array $dirs = [];
    private int $lists = 0;

    public function __construct(string $workerDir, string $jobDir, ?string $cwd = null)
    {
        $this->workerDir = rtrim($workerDir, '/');
        $this->jobDir = rtrim($jobDir, '/');
        $cwd = null !== $cwd ? rtrim($cwd, '/') : null;
        $this->cwd = null !== $cwd && ($cwd === $this->workerDir || str_starts_with($cwd, $this->workerDir.'/')) ? $cwd : null;
        if ('' === $this->workerDir || !FileTree::isRealDirectory($this->workerDir)) {
            throw new RemoteExecException(sprintf('The worker directory "%s" does not exist', $workerDir));
        }
        if (!FileTree::isRealDirectory($this->jobDir)) {
            throw new RemoteExecException(sprintf('The job directory "%s" does not exist', $jobDir));
        }
    }

    /**
     * Links the files of the command into the job directory.
     *
     * @param list<string> $args
     *
     * @return list<string> the arguments, with the paths of the job directory
     *
     * @throws UnmappablePathException
     */
    public function map(Tool $tool, array $args): array
    {
        $args = array_values($args);
        $mapped = [];
        $concatInput = false;
        $count = count($args);
        for ($i = 0; $i < $count; ++$i) {
            $arg = $args[$i];

            if (Tool::Ffmpeg === $tool && $i + 1 < $count) {
                if ('-f' === $arg) {
                    // A format option applies to the next input (or output) only
                    $concatInput = 'concat' === $args[$i + 1];
                    $mapped[] = $arg;
                    $mapped[] = $this->mapArgument($args[++$i]);
                    continue;
                }
                if ('-i' === $arg) {
                    $mapped[] = $arg;
                    $mapped[] = $concatInput ? $this->mapConcatList($args[++$i]) : $this->mapArgument($args[++$i]);
                    $concatInput = false;
                    continue;
                }
                if ('-passlogfile' === $arg) {
                    $mapped[] = $arg;
                    $mapped[] = $this->mapPassLogFile($args[++$i]);
                    continue;
                }
            }

            $mapped[] = $this->mapArgument($arg);
        }

        return $mapped;
    }

    /**
     * Renames into the worker directory what the tool created or replaced in the job directory.
     * To call once the conversion container has cleaned the job directory up (only regular files
     * and directories left, owned by the worker).
     */
    public function syncBack(): void
    {
        foreach ($this->dirs as $origin => $dir) {
            $this->syncDirectory($dir['job'], $origin, $dir['links'], '');
        }
    }

    private function mapArgument(string $arg): string
    {
        // A relative path to a file of the worker directory (the tool runs in the job directory)
        if (null !== $this->cwd && '' !== $arg && !str_starts_with($arg, '/') && !str_starts_with($arg, '-')
            && !str_contains($arg, '://') && false !== @lstat($this->cwd.'/'.$arg)) {
            return $this->mapPath($this->cwd.'/'.$arg);
        }

        if (!str_contains($arg, $this->workerDir)) {
            if (str_starts_with($arg, '/') && !in_array($arg, self::ALLOWED_OUTSIDE_PATHS, true) && file_exists($arg)) {
                throw new UnmappablePathException(sprintf('"%s" is not under %s: the conversion container cannot read it', $arg, $this->workerDir));
            }

            return $arg;
        }

        // The whole argument is a path, or a file URL
        if (str_starts_with($arg, '/')) {
            return $this->mapPath($arg);
        }
        if (str_starts_with($arg, 'file:///')) {
            return self::toFileUrl($this->mapPath(rawurldecode(substr($arg, 7))));
        }

        // key=/path or key=file:///path, with nothing that could delimit filter options after the key
        if (preg_match('#^([^=/\s]+=)(file://)?(/[^\s\'"\[\];,|]*)$#', $arg, $matches)) {
            [, $key, $scheme, $path] = $matches;

            return $key.('' !== $scheme
                ? self::toFileUrl($this->mapPath(rawurldecode($path)))
                : $this->mapPath($path));
        }

        // Paths embedded in a filter: inputs only, which must exist
        $pattern = '#'.preg_quote($this->workerDir, '#').'(?:/'.self::EMBEDDED_PATH_CHARS.'*)?#';
        $result = preg_replace_callback($pattern, fn (array $m): string => $this->mapPath($m[0], true), $arg);
        if (null === $result) {
            throw new UnmappablePathException('Cannot parse an argument referring to the worker directory');
        }

        return $result;
    }

    /**
     * The -passlogfile option of ffmpeg is a prefix, not a file: ffmpeg writes <prefix>-0.log…
     * next to it, and reads those of the earlier pass (php-ffmpeg even makes the prefix a
     * directory). It keeps its name in the job directory of its parent, along with the statistics.
     */
    private function mapPassLogFile(string $arg): string
    {
        if (!str_starts_with($arg, $this->workerDir.'/')) {
            return $this->mapArgument($arg);
        }

        $this->assertMappable($arg);
        $dir = dirname($arg);
        if (!FileTree::isRealDirectory($dir)) {
            throw new UnmappablePathException(sprintf('The directory of the pass log prefix "%s" does not exist', $arg));
        }
        $job = $this->mapDirectory($dir, false);

        $prefix = basename($arg).'-';
        foreach (@scandir($dir) ?: [] as $entry) {
            if (str_starts_with($entry, $prefix) && FileTree::isRegularFile($dir.'/'.$entry)) {
                $this->linkInto($dir, $entry);
            }
        }

        return $job.'/'.basename($arg);
    }

    /**
     * The list read by the concat demuxer names its files itself: it is rewritten in the job
     * directory, with the paths of the job.
     */
    private function mapConcatList(string $arg): string
    {
        if (!str_starts_with($arg, $this->workerDir.'/')) {
            return $this->mapArgument($arg);
        }

        $this->assertMappable($arg);
        if (!FileTree::isRegularFile($arg)) {
            throw new UnmappablePathException(sprintf('The concat list "%s" does not exist', $arg));
        }
        $content = @file_get_contents($arg, false, null, 0, self::MAX_CONCAT_LIST_SIZE + 1);
        if (false === $content || strlen($content) > self::MAX_CONCAT_LIST_SIZE) {
            throw new UnmappablePathException(sprintf('Cannot read the concat list "%s"', $arg));
        }

        $lines = [];
        foreach (preg_split('/\r?\n/', $content) as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^file\s+(.*)$/s', $trimmed, $matches)) {
                $path = self::parseConcatToken($matches[1]);
                if (!str_starts_with($path, '/')) {
                    $path = dirname($arg).'/'.$path;
                }
                if (!str_starts_with($path, $this->workerDir.'/')) {
                    throw new UnmappablePathException(sprintf('The concat list "%s" names "%s", which is not under %s', $arg, $path, $this->workerDir));
                }
                $lines[] = 'file '.self::quoteConcatToken($this->mapPath($path, true));
            } elseif (str_contains($line, $this->workerDir)) {
                throw new UnmappablePathException(sprintf('Unsupported line in the concat list "%s": %s', $arg, $line));
            } else {
                $lines[] = $line;
            }
        }

        $listPath = sprintf('%s/concat-%d.txt', $this->jobDir, $this->lists++);
        if (false === @file_put_contents($listPath, implode("\n", $lines))) {
            throw new RemoteExecException('Cannot write the concat list in the job directory');
        }

        return $listPath;
    }

    /**
     * @param bool $mustExist whether the path can only be an input (embedded in a filter, listed…)
     */
    private function mapPath(string $path, bool $mustExist = false): string
    {
        $this->assertMappable($path);

        // Within a directory mirrored as a whole (the deepest one)
        $mirror = null;
        foreach ($this->dirs as $origin => $dir) {
            if ($dir['full'] && str_starts_with($path, $origin.'/') && (null === $mirror || strlen($origin) > strlen($mirror))) {
                $mirror = $origin;
            }
        }
        if (null !== $mirror) {
            $jobPath = $this->dirs[$mirror]['job'].substr($path, strlen($mirror));
            if ($mustExist && false === @lstat($jobPath)) {
                throw new UnmappablePathException(sprintf('"%s" does not exist', $path));
            }

            return $jobPath;
        }

        clearstatcache();
        $stat = @lstat($path);
        if (false === $stat) {
            if ($mustExist) {
                throw new UnmappablePathException(sprintf('"%s" does not exist', $path));
            }
            $parent = dirname($path);
            if (!FileTree::isRealDirectory($parent)) {
                throw new UnmappablePathException(sprintf('Neither "%s" nor its directory exist', $path));
            }

            return $this->mapDirectory($parent, false).'/'.basename($path);
        }

        if (FileTree::isRegularFileMode($stat['mode'])) {
            $parent = dirname($path);
            $this->linkInto($parent, basename($path));

            return $this->dirs[$parent]['job'].'/'.basename($path);
        }
        if (FileTree::isDirectoryMode($stat['mode'])) {
            if ($mustExist) {
                throw new UnmappablePathException(sprintf('"%s" is a directory, a file is expected', $path));
            }

            return $this->mapDirectory($path, true);
        }

        throw new UnmappablePathException(sprintf('"%s" is neither a file nor a directory', $path));
    }

    /**
     * A path of the worker directory, without any relative segment or symbolic link to leave it.
     */
    private function assertMappable(string $path): void
    {
        if ($path === $this->workerDir) {
            throw new UnmappablePathException(sprintf('The worker directory %s itself cannot be handed to the conversion container', $path));
        }
        if (!str_starts_with($path, $this->workerDir.'/')) {
            throw new UnmappablePathException(sprintf('"%s" is not under %s', $path, $this->workerDir));
        }

        $segments = explode('/', substr($path, strlen($this->workerDir) + 1));
        $current = $this->workerDir;
        foreach ($segments as $i => $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw new UnmappablePathException(sprintf('"%s" is not a normalized path', $path));
            }
            $current .= '/'.$segment;
            $isLast = $i === count($segments) - 1;
            $stat = @lstat($current);
            if (false === $stat) {
                if (!$isLast) {
                    break;
                }
            } elseif (!$isLast && !FileTree::isDirectoryMode($stat['mode'])) {
                throw new UnmappablePathException(sprintf('"%s" goes through "%s", which is not a directory', $path, $current));
            } elseif ($isLast && !FileTree::isDirectoryMode($stat['mode']) && !FileTree::isRegularFileMode($stat['mode'])) {
                throw new UnmappablePathException(sprintf('"%s" is neither a file nor a directory', $path));
            }
        }
    }

    private function mapDirectory(string $origin, bool $full): string
    {
        if (!isset($this->dirs[$origin])) {
            $job = sprintf('%s/%d', $this->jobDir, count($this->dirs));
            if (!@mkdir($job, 0700)) {
                throw new RemoteExecException(sprintf('Cannot create "%s"', $job));
            }
            $this->dirs[$origin] = ['job' => $job, 'full' => false, 'links' => []];
        }

        if ($full && !$this->dirs[$origin]['full']) {
            $this->dirs[$origin]['full'] = true;
            $this->mirror($origin, '');
        }

        return $this->dirs[$origin]['job'];
    }

    private function mirror(string $origin, string $relative): void
    {
        $source = '' === $relative ? $origin : $origin.'/'.$relative;
        $entries = @scandir($source);
        if (false === $entries) {
            throw new UnmappablePathException(sprintf('Cannot read the directory "%s"', $source));
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $entryRelative = '' === $relative ? $entry : $relative.'/'.$entry;
            $stat = @lstat($origin.'/'.$entryRelative);
            if (false === $stat) {
                continue;
            }
            if (FileTree::isDirectoryMode($stat['mode'])) {
                $target = $this->dirs[$origin]['job'].'/'.$entryRelative;
                if (!is_dir($target) && !@mkdir($target, 0700)) {
                    throw new RemoteExecException(sprintf('Cannot create "%s"', $target));
                }
                $this->mirror($origin, $entryRelative);
            } elseif (FileTree::isRegularFileMode($stat['mode'])) {
                $this->linkInto($origin, $entryRelative);
            }
        }
    }

    private function linkInto(string $origin, string $relative): void
    {
        $this->mapDirectory($origin, false);
        if (isset($this->dirs[$origin]['links'][$relative])) {
            return;
        }

        $source = $origin.'/'.$relative;
        $target = $this->dirs[$origin]['job'].'/'.$relative;
        clearstatcache();
        $inode = @fileinode($source);
        if (false === $inode || !@link($source, $target)) {
            throw new UnmappablePathException(sprintf('Cannot link "%s" into the job directory', $source));
        }
        $this->dirs[$origin]['links'][$relative] = $inode;
    }

    /**
     * @param array<string, int> $links
     */
    private function syncDirectory(string $job, string $origin, array $links, string $relative): void
    {
        $source = '' === $relative ? $job : $job.'/'.$relative;
        $entries = @scandir($source);
        if (false === $entries) {
            throw new RemoteExecException(sprintf('Cannot read the job directory "%s"', $source));
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $entryRelative = '' === $relative ? $entry : $relative.'/'.$entry;
            $jobPath = $job.'/'.$entryRelative;
            $originPath = $origin.'/'.$entryRelative;
            clearstatcache();
            $stat = @lstat($jobPath);
            if (false === $stat) {
                continue;
            }

            if (FileTree::isDirectoryMode($stat['mode'])) {
                if (!FileTree::isRealDirectory($originPath)) {
                    if (false !== @lstat($originPath) || !@mkdir($originPath, 0700)) {
                        throw new RemoteExecException(sprintf('Cannot bring the directory "%s" back', $originPath));
                    }
                }
                $this->syncDirectory($job, $origin, $links, $entryRelative);
            } elseif (FileTree::isRegularFileMode($stat['mode'])) {
                if (isset($links[$entryRelative]) && $links[$entryRelative] === $stat['ino']) {
                    continue;
                }
                if (FileTree::isRealDirectory($originPath) || !@rename($jobPath, $originPath)) {
                    throw new RemoteExecException(sprintf('Cannot bring "%s" back', $originPath));
                }
            }
        }
    }

    /**
     * A token of the concat demuxer: quotes and backslashes as in av_get_token().
     */
    private static function parseConcatToken(string $text): string
    {
        $text = trim($text);
        $token = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];
            if ('\\' === $char && $i + 1 < $length) {
                $token .= $text[++$i];
            } elseif ("'" === $char) {
                $end = strpos($text, "'", $i + 1);
                if (false === $end) {
                    throw new UnmappablePathException('Unterminated quote in a concat list');
                }
                $token .= substr($text, $i + 1, $end - $i - 1);
                $i = $end;
            } elseif (ctype_space($char)) {
                break;
            } else {
                $token .= $char;
            }
        }

        return $token;
    }

    private static function quoteConcatToken(string $path): string
    {
        return "'".str_replace("'", "'\\''", $path)."'";
    }

    private static function toFileUrl(string $path): string
    {
        return 'file://'.implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
