<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use Psr\Log\LoggerInterface;

abstract class AbstractCachedFaker extends BaseProvider
{
    public function __construct(
        private readonly ?string $fixturesCacheDir,
        protected readonly FileStorageManager $fileStorageManager,
        protected readonly PathGeneratorInterface $pathGenerator,
        Generator $generator,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct($generator);
    }

    /**
     * @return resource
     */
    protected function download(string $cachePath, string $extension, string $url): mixed
    {
        if (null !== $this->fixturesCacheDir) {
            $cacheKey = $this->getFileCacheKey($cachePath, $extension);
            if (!file_exists($cacheKey)) {
                $this->logger->debug(sprintf('Fetching "%s"', $url));
                $resource = $this->openUrl($url);

                try {
                    $this->cacheFile($cachePath, $extension, $resource);
                } finally {
                    fclose($resource);
                }
            }

            $stream = fopen($cacheKey, 'r');
            if (!is_resource($stream)) {
                throw new \InvalidArgumentException(sprintf('Cannot open cached file "%s"', $url));
            }
        } else {
            $stream = $this->openUrl($url);
        }

        return $stream;
    }

    /**
     * @return resource
     */
    private function openUrl(string $url): mixed
    {
        // Silenced so that an unavailable URL (e.g. HTTP 403/404) always ends up in the exception below
        error_clear_last();
        $resource = @fopen($url, 'r');
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException(sprintf('Cannot open URL "%s": %s', $url, trim(error_get_last()['message'] ?? 'unknown error')));
        }

        return $resource;
    }

    protected function downloadAndStore(string $pathPrefix, string $cachePath, string $extension, string $url): string
    {
        $stream = $this->download($cachePath, $extension, $url);

        try {
            $finalPath = $this->pathGenerator->generatePath($extension, $pathPrefix.'/');
            $this->fileStorageManager->storeStream($finalPath, $stream);
        } finally {
            fclose($stream);
        }

        return $finalPath;
    }

    private function ensureCacheDirExists(string $cacheKey): void
    {
        $dir = dirname($cacheKey);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    protected function cacheFile(string $cachePath, string $extension, $resource): void
    {
        $cacheKey = $this->getFileCacheKey($cachePath, $extension);
        $this->ensureCacheDirExists($cacheKey);

        file_put_contents($cacheKey, $resource);
    }

    protected function cacheFileFromPath(string $cachePath, string $extension, string $src): void
    {
        $cacheKey = $this->getFileCacheKey($cachePath, $extension);
        $this->ensureCacheDirExists($cacheKey);

        copy($src, $cacheKey);
    }

    protected function getCachedFile(string $cachePath, string $extension): ?string
    {
        $cacheKey = $this->getFileCacheKey($cachePath, $extension);
        if (file_exists($cacheKey)) {
            return $cacheKey;
        }

        return null;
    }

    private function getFileCacheKey(string $cachePath, string $extension): string
    {
        return $this->fixturesCacheDir.'/'.$cachePath.'.'.$extension;
    }
}
