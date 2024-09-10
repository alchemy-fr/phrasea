<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;
use Liip\ImagineBundle\Imagine\Filter\Loader\WatermarkFilterLoader;

final readonly class WatermarkDownloadProxyFilterLoader implements LoaderInterface
{
    public function __construct(
        private TransformationContext $context,
        private ImagineInterface $imagine,
    )
    {
    }

    public function load(ImageInterface $image, array $options = [])
    {
        $path = $this->context->getRemoteFile($options['image']);

        $projectDir = dirname($path);

        $loader = new WatermarkFilterLoader($this->imagine, $projectDir);

        $options['image'] = basename($path);

        return $loader->load($image, $options);
    }
}
