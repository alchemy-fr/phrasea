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
    private readonly FileStorageManager $fileStorageManager;
    private readonly PathGenerator $pathGenerator;

    public function __construct(
        private readonly ?string $fixturesCacheDir,
        FileStorageManager $fileStorageManager,
        PathGenerator $pathGenerator,
        Generator $generator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($generator);
        $this->fileStorageManager = $fileStorageManager;
        $this->pathGenerator = $pathGenerator;
    }

    protected function download(string $pathPrefix, string $cachePath, string $extension, string $url): string
    {
        $this->logger->debug(sprintf('Fetching "%s"', $url));
        if (null !== $this->fixturesCacheDir) {
            if (!is_dir($this->fixturesCacheDir)) {
                mkdir($this->fixturesCacheDir);
            }
            $cacheKey = $this->fixturesCacheDir.'/'.$cachePath.'.'.$extension;
            if (!file_exists($cacheKey)) {
                $resource = fopen($url, 'r');
                if (!is_resource($resource)) {
                    throw new \InvalidArgumentException(sprintf('Cannot open URL "%s"', $url));
                }

                file_put_contents($cacheKey, $resource);
                fclose($resource);
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

        $finalPath = $this->pathGenerator->generatePath($extension, $pathPrefix.'/');
        $this->fileStorageManager->storeStream($finalPath, $stream);
        fclose($stream);

        return $finalPath;
    }
}
