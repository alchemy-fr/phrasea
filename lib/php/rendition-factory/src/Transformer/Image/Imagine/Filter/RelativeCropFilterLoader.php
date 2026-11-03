<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine\Filter;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

/**
 * Crops a rectangle given relatively to the image size (0..1), as measured when the filter runs:
 * put it after "auto_rotate" for a rectangle drawn on the displayed image.
 */
class RelativeCropFilterLoader implements LoaderInterface
{
    public function load(ImageInterface $image, array $options = [])
    {
        $size = $image->getSize();
        $imageWidth = $size->getWidth();
        $imageHeight = $size->getHeight();

        [$x, $width] = self::toPixels((float) ($options['x'] ?? 0), (float) ($options['width'] ?? 1), $imageWidth);
        [$y, $height] = self::toPixels((float) ($options['y'] ?? 0), (float) ($options['height'] ?? 1), $imageHeight);

        if (0 === $x && 0 === $y && $width === $imageWidth && $height === $imageHeight) {
            return $image;
        }

        return $image->crop(new Point($x, $y), new Box($width, $height));
    }

    /**
     * @return array{int, int} the start and the length, at least 1px, within the image
     */
    private static function toPixels(float $start, float $length, int $total): array
    {
        $length = max(1, min($total, (int) round($length * $total)));
        $start = max(0, min($total - $length, (int) round($start * $total)));

        return [$start, $length];
    }
}
