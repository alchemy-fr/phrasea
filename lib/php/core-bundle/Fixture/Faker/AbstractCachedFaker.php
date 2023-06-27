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
        private readonly ?string $cacheDir,
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
        if (null !== $this->cacheDir) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir);
            }
            $cacheKey = $this->cacheDir.'/'.$cachePath.'.'.$extension;
            if (!file_exists($cacheKey)) {
                file_put_contents($cacheKey, fopen($url, 'r'));
            }

            $stream = fopen($cacheKey, 'r');
        } else {
            $stream = fopen($url, 'r');
        }

        $finalPath = $this->pathGenerator->generatePath($extension, $pathPrefix.'/');
        $this->fileStorageManager->storeStream($finalPath, $stream);
        fclose($stream);

        return $finalPath;
    }
}
