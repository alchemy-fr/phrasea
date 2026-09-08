<?php

declare(strict_types=1);

namespace App\Service\Image;

/**
 * Produces a lighter JPEG copy of an image before sending it to an external service
 * (face recognition, embedding...), so that large renditions or sources stay under the
 * upload limits of these services.
 */
final readonly class ImageDownscaler
{
    final public const int DEFAULT_QUALITY = 85;

    public function __construct(
        private ImageManagerFactory $imageManagerFactory,
    ) {
    }

    /**
     * Returns the path of a JPEG copy whose longest side does not exceed $maxSize pixels.
     * The original path is returned untouched when the image is already small enough
     * (and, if $maxBytes is given, light enough): compare the returned path with the
     * original one to know whether a temporary file must be deleted afterwards.
     */
    public function downscale(string $path, int $maxSize, ?int $maxBytes = null, int $quality = self::DEFAULT_QUALITY): string
    {
        if ($maxSize <= 0) {
            throw new \InvalidArgumentException('Max size must be a positive number of pixels');
        }

        $image = $this->imageManagerFactory->createManager()->make($path);
        $image->orientate();

        $fitsInPixels = $image->width() <= $maxSize && $image->height() <= $maxSize;
        $fitsInBytes = null === $maxBytes || filesize($path) <= $maxBytes;
        $isWebFormat = in_array($image->mime(), ['image/jpeg', 'image/png'], true);

        if ($fitsInPixels && $fitsInBytes && $isWebFormat) {
            return $path;
        }

        if (!$fitsInPixels) {
            $image->resize($maxSize, $maxSize, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        $target = tempnam(sys_get_temp_dir(), 'downscale-').'.jpg';
        $image->save($target, $quality, 'jpg');

        return $target;
    }
}
