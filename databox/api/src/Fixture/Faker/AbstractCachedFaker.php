<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Storage\RenditionPathGenerator;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;

abstract class AbstractCachedFaker extends BaseProvider
{
    private FileStorageManager $fileStorageManager;
    private RenditionPathGenerator $pathGenerator;
    private ?string $cacheDir = null;

    public function __construct(
        string $fixturesCacheDir,
        FileStorageManager $fileStorageManager,
        RenditionPathGenerator $pathGenerator,
        Generator $generator
    ) {
        parent::__construct($generator);
        $this->cacheDir = $fixturesCacheDir;
        $this->fileStorageManager = $fileStorageManager;
        $this->pathGenerator = $pathGenerator;
    }

    protected function download(string $workspaceId, string $cachePath, string $extension, string $url): string
    {
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
