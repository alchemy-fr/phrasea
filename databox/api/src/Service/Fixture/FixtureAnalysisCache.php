<?php

declare(strict_types=1);

namespace App\Service\Fixture;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Disk cache of the responses of the ML analysis services (faces, visual similarity), only meant for
 * replaying the dev fixtures: `bin/dev/reset-db-fixtures.sh` enables it with FIXTURES_ANALYSIS_CACHE on
 * the ingest command alone, so that a replay does not send the same images to the services again.
 *
 * The key is the content of the file actually sent (never the asset or the rendition, whose ids change
 * on every reset): a cached response always belongs to the very same image.
 * Delete the cache directory after a change of model or service.
 */
final readonly class FixtureAnalysisCache
{
    public function __construct(
        #[Autowire(env: 'bool:default::FIXTURES_ANALYSIS_CACHE')]
        private bool $enabled,
        #[Autowire('%kernel.project_dir%/var/fixtures/analysis')]
        private string $cacheDir,
    ) {
    }

    /**
     * @template T of array
     *
     * @param \Closure(): T $compute
     *
     * @return T
     */
    public function remember(string $namespace, string $path, \Closure $compute): array
    {
        if (!$this->enabled) {
            return $compute();
        }

        $hash = hash_file('sha256', $path);
        if (false === $hash) {
            return $compute();
        }

        $dir = $this->cacheDir.'/'.$namespace;
        $file = $dir.'/'.$hash.'.json';

        if (is_file($file)) {
            /** @var T|null $cached */
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $compute();

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return $result;
        }

        // Written aside then renamed: an interrupted run never leaves a truncated response behind.
        $tmp = tempnam($dir, $hash.'.');
        if (false === $tmp) {
            return $result;
        }
        if (false === file_put_contents($tmp, json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION))
            || !chmod($tmp, 0644)
            || !rename($tmp, $file)) {
            @unlink($tmp);
        }

        return $result;
    }
}
