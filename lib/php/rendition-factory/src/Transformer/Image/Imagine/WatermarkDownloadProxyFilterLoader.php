<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Alchemy\RenditionFactory\Context\TransformationContext;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;
use Liip\ImagineBundle\Imagine\Filter\Loader\WatermarkFilterLoader;

final readonly class WatermarkDownloadProxyFilterLoader implements LoaderInterface
{
    public function __construct(
        private TransformationContextInterface $context,
        private ImagineInterface $imagine,
    ) {
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
