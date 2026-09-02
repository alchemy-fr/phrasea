<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine\Filter;

use Imagine\Image\ImageInterface;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

/**
 * Grayscale conversion through Imagine effects (native Imagick/GD operation),
 * unlike Liip's GrayscaleFilterLoader which iterates over every pixel.
 */
class GrayscaleFilterLoader implements LoaderInterface
{
    public function load(ImageInterface $image, array $options = [])
    {
        $image->effects()->grayscale();

        return $image;
    }
}
