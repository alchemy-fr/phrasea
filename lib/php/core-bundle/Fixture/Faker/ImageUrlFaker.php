<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class ImageUrlFaker extends AbstractCachedFaker
{
    private const string BUCKET_URL = 'https://alchemy-phrasea-fixtures.s3.eu-west-3.amazonaws.com/fixtures/images';

    /**
     * Always available on the bucket: images missing from another theme are taken from it.
     */
    private const string FALLBACK_THEME = 'nature';

    /**
     * @var array<string, true> cache paths of the theme images that could not be fetched
     */
    private array $unavailableImages = [];

    public function imageUrlRandomRatio(
        string $workspaceId,
        string $lock,
        int $size = 1000,
        ?string $theme = null,
    ): string {
        if ($size <= 0) {
            throw new \InvalidArgumentException(sprintf('Size must be greater than 0, got %d', $size));
        }

        $lockNumber = $this->extractLockNumber($lock);

        $ratios = [
            16 / 9,
            1,
            4 / 3,
            3 / 4,
            9 / 16,
        ];
        $ratio = $ratios[$lockNumber % count($ratios)];

        if ($ratio >= 1) {
            $width = $size;
            $height = $size / $ratio;
        } else {
            $width = $size * $ratio;
            $height = $size;
        }

        return $this->image($width, $height, $lock, $workspaceId, $theme);
    }

    public function image(
        float|int $width,
        float|int $height,
        string $lockNumber,
        string $pathPrefix,
        ?string $theme = null,
    ): string {
        $width = (int) round($width);
        $height = (int) round($height);

        $totalVariants = 42;
        $lockNumber = $this->extractLockNumber($lockNumber);
        $lockNorm = ($lockNumber % $totalVariants) + 1;
        $extension = 'jpg';

        if (filter_var(getenv('FIXTURES_GENERATE_IMAGES'), FILTER_VALIDATE_BOOLEAN)) {
            return $this->generateSolidColorImage(
                $width,
                $height,
                $pathPrefix,
                $extension,
            );
        }

        $orientation = $width >= $height ? 'landscape' : 'portrait';
        [$cachePath, $imageSrc] = $this->storeThemeImage(
            $pathPrefix,
            $theme ?? self::FALLBACK_THEME,
            $orientation,
            $lockNorm,
            $extension,
        );

        $croppedCacheKKey = $cachePath.sprintf('-%d-%d', $width, $height);
        if (null !== $cached = $this->getCachedFile($croppedCacheKKey, $extension)) {
            return $this->storeOnBucket($pathPrefix, $extension, $cached);
        }

        $stream = $this->fileStorageManager->getStream($imageSrc);
        $tmpImage = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmpImage, $stream);
        fclose($stream);

        $imagick = new \Imagick($tmpImage);

        $imagick->cropThumbnailImage($width, $height);
        $imagick->setImagePage(0, 0, 0, 0);

        try {
            $imagick->writeImage($tmpImage);
            $imagick->clear();
            $imagick->destroy();

            $this->cacheFileFromPath(
                $croppedCacheKKey,
                $extension,
                $tmpImage,
            );

            return $this->storeOnBucket($pathPrefix, $extension, $tmpImage);
        } finally {
            unlink($tmpImage);
        }
    }

    /**
     * Themes are filled by hand on the bucket: a variant missing from the requested theme is
     * replaced by the same variant of the fallback theme, so that fixtures always load.
     *
     * @return array{string, string} the cache path of the image actually used, and its stored path
     */
    private function storeThemeImage(
        string $pathPrefix,
        string $theme,
        string $orientation,
        int $variant,
        string $extension,
    ): array {
        $cachePath = sprintf('%s/%s-%d', $theme, $orientation, $variant);
        $url = sprintf('%s/%s/%s/%d.%s', self::BUCKET_URL, $theme, $orientation, $variant, $extension);

        if (self::FALLBACK_THEME === $theme) {
            return [$cachePath, $this->downloadAndStore($pathPrefix, $cachePath, $extension, $url)];
        }

        if (!isset($this->unavailableImages[$cachePath])) {
            try {
                return [$cachePath, $this->downloadAndStore($pathPrefix, $cachePath, $extension, $url)];
            } catch (\InvalidArgumentException $e) {
                $this->unavailableImages[$cachePath] = true;
                $this->logger->warning(sprintf('Image theme "%s" unavailable, falling back to "%s": %s', $theme, self::FALLBACK_THEME, $e->getMessage()));
            }
        }

        return $this->storeThemeImage($pathPrefix, self::FALLBACK_THEME, $orientation, $variant, $extension);
    }

    private function generateSolidColorImage(
        int $width,
        int $height,
        string $pathPrefix,
        string $extension,
    ): string {
        $cachePath = sprintf('generated/%d-%d', $width, $height);
        if (null !== $cached = $this->getCachedFile($cachePath, $extension)) {
            return $cached;
        }

        $imagick = new \Imagick();
        $imagick->newImage($width, $height, new \ImagickPixel('gray'));
        $imagick->setImageFormat($extension);

        $tmpImage = tempnam(sys_get_temp_dir(), 'img');
        try {
            $imagick->writeImage($tmpImage);
            $imagick->clear();
            $imagick->destroy();

            $this->cacheFileFromPath(
                $cachePath,
                $extension,
                $tmpImage,
            );

            return $this->storeOnBucket($pathPrefix, $extension, $tmpImage);
        } finally {
            unlink($tmpImage);
        }
    }

    private function storeOnBucket(string $pathPrefix, string $extension, string $src): string
    {
        $newPath = $this->pathGenerator->generatePath($extension, $pathPrefix.'/');
        $fd = fopen($src, 'r');
        try {
            $this->fileStorageManager->storeStream($newPath, $fd);
        } finally {
            fclose($fd);
        }

        return $newPath;
    }

    private function extractLockNumber(string $lock): int
    {
        if (!preg_match('#(\d+)$#', $lock, $matches)) {
            throw new \InvalidArgumentException(sprintf('Lock must end with a number, got "%s"', $lock));
        }

        return (int) $matches[1];
    }
}
