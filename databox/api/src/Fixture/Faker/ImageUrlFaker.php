<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Storage\RenditionPathGenerator;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

class ImageUrlFaker extends BaseProvider
{
    private Client $client;
    private FileStorageManager $fileStorageManager;
    private RenditionPathGenerator $pathGenerator;
    private ?string $cacheDir = null;

    public function __construct(Generator $generator)
    {
        parent::__construct($generator);
        $this->client = new Client([
            'stream' => true,
        ]);
    }

    /**
     * @required
     */
    public function setFileStorageManager(FileStorageManager $fileStorageManager): void
    {
        $this->fileStorageManager = $fileStorageManager;
    }

    /**
     * @required
     */
    public function setPathGenerator(RenditionPathGenerator $pathGenerator): void
    {
        $this->pathGenerator = $pathGenerator;
    }

    /**
     * @required
     */
    public function setCacheDir(?string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

    public function imageUrlRandomRatio(
        string $workspaceId,
        string $lock,
        int $size = 1000,
        $theme = 'landscape'
    ): string
    {
        $ratios = [
            16 / 9,
            1,
            4 / 3,
            3 / 4,
            9 / 16,
        ];
        $ratio = $ratios[(int)$lock % count($ratios)];

        if ($ratio >= 1) {
            $width = $size;
            $height = $size / $ratio;
        } else {
            $width = $size * $ratio;
            $height = $size;
        }
        $width = round($width);
        $height = round($height);

        $baseUrl = 'https://loremflickr.com';

        $url = sprintf($baseUrl.'/%s/%s/%s?lock=%s',
            $width,
            $height,
            $theme,
            $lock
        );
        $extension = 'jpg';

        if (null !== $this->cacheDir) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir);
            }
            $disc = sprintf('%s-%s-%s-%s', $theme, $lock, $width, $height);
            $cacheKey = $this->cacheDir.'/'.$disc.'.'.$extension;
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
