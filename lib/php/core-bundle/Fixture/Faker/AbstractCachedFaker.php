<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use Psr\Log\LoggerInterface;

abstract class AbstractCachedFaker extends BaseProvider
{
    public function __construct(
        private readonly ?string $fixturesCacheDir,
        protected readonly FileStorageManager $fileStorageManager,
        protected readonly PathGenerator $pathGenerator,
        Generator $generator,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($generator);
    }

    protected function download(string $pathPrefix, string $cachePath, string $extension, string $url): string
    {
        $this->logger->debug(sprintf('Fetching "%s"', $url));
        if (null !== $this->fixturesCacheDir) {
            $cacheKey = $this->getFileCacheKey($cachePath, $extension);
            if (!file_exists($cacheKey)) {
                $resource = fopen($url, 'r');
                if (!is_resource($resource)) {
                    throw new \InvalidArgumentException(sprintf('Cannot open URL "%s"', $url));
                }

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
            $stream = fopen($url, 'r');
            if (!is_resource($stream)) {
                throw new \InvalidArgumentException(sprintf('Cannot open URL "%s"', $url));
            }
        }

        try {
            $finalPath = $this->pathGenerator->generatePath($extension, $pathPrefix.'/');
            $this->fileStorageManager->storeStream($finalPath, $stream);
        } finally {
            fclose($stream);
        }

        return $finalPath;
    }

    protected function cacheFile(string $cachePath, string $extension, $resource, ?string &$cacheKey = null): void
    {
        $cacheKey = $this->fixturesCacheDir.'/'.$cachePath.'.'.$extension;
        $dir = dirname($cacheKey);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($cacheKey, $resource);
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
