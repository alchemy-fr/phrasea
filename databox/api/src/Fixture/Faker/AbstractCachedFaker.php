<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Storage\FilePathGenerator;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use Psr\Log\LoggerInterface;

abstract class AbstractCachedFaker extends BaseProvider
{
    private FileStorageManager $fileStorageManager;
    private FilePathGenerator $pathGenerator;
    private LoggerInterface $logger;
    private ?string $cacheDir = null;

    public function __construct(
        string $fixturesCacheDir,
        FileStorageManager $fileStorageManager,
        FilePathGenerator $pathGenerator,
        Generator $generator,
        LoggerInterface $logger
    ) {
        parent::__construct($generator);
        $this->cacheDir = $fixturesCacheDir;
        $this->fileStorageManager = $fileStorageManager;
        $this->pathGenerator = $pathGenerator;
        $this->logger = $logger;
    }

    protected function download(string $workspaceId, string $cachePath, string $extension, string $url): string
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

        $finalPath = $this->pathGenerator->generatePath($workspaceId, $extension);
        $this->fileStorageManager->storeStream($finalPath, $stream);
        fclose($stream);

        return $finalPath;
    }
}
