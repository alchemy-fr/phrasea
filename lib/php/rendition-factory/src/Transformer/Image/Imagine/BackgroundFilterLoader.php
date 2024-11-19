<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

class BackgroundFilterLoader implements LoaderInterface
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
        $imageSize = $image->getSize();

        $imageW = $imageSize->getWidth();
        $imageH = $imageSize->getHeight();

        $canvas = $this->imagine->create($imageSize, $background);

        /**
         * @var ImageInterface $layer
         */
        foreach ($image->layers() as $layer) {
            $layerSize = $layer->getSize();
            $layerDW = ($imageW - $layerSize->getWidth()) / 2;
            $layerDH = ($imageH - $layerSize->getHeight()) / 2;
            $canvas->paste($layer, new Point($layerDW, $layerDH));
        }

        return $canvas;
    }
}
