<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

class BackgroundFillFilterLoader implements LoaderInterface
{
    public function __construct(protected ImagineInterface $imagine)
    {
    }

    public function load(ImageInterface $image, array $options = [])
    {
        $background = $image->palette()->color(
            $options['color'] ?? '#fff',
            $options['opacity'] ?? 100,
        );
        $canvas = $this->imagine->create($image->getSize(), $background);

        // This is a workaround to avoid a bug in Imagine that causes wrong positionning
        // when the image has multiple layers
        $unused = $image->layers()[0];

        $canvas->paste($image, new Point(0, 0));

        return $canvas;
    }
}
