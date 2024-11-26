<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Alchemy\RenditionFactory\Transformer\Image\Imagine\Filter\StampFilter;
use Imagine\Image\ImagineInterface;
use Liip\ImagineBundle\Binary\SimpleMimeTypeGuesser;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Imagine\Filter\Loader\AutoRotateFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\CropFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\DownscaleFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\FixedFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\FlipFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\InterlaceFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\RelativeResizeFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\ResampleFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\ResizeFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\RotateFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\ScaleFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\StripFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\ThumbnailFilterLoader;
use Liip\ImagineBundle\Imagine\Filter\Loader\UpscaleFilterLoader;
use Symfony\Component\Mime\MimeTypes;

final readonly class ImagineFilterFactory
{
    public function __construct(
        private ImagineInterface $imagine,
        private TemplateResolverInterface $templateResolver,
    ) {
    }

    public function createFilterManager(TransformationContextInterface $context): FilterManager
    {
        $filterManager = new FilterManager(
            new FilterConfiguration([]),
            $this->imagine,
            new SimpleMimeTypeGuesser(new MimeTypes())
        );

        $filters = $this->createFilterLoaders($context);

        foreach ($filters as $name => $filter) {
            $filterManager->addLoader($name, $filter);
        }

        return $filterManager;
    }

    public function createFilterLoaders(TransformationContextInterface $context): array
    {
        return [
            'relative_resize' => new RelativeResizeFilterLoader(),
            'resize' => new ResizeFilterLoader(),
            'thumbnail' => new ThumbnailFilterLoader(),
            'crop' => new CropFilterLoader(),
            //            'grayscale' => new GrayscaleFilterLoader(), Disabled because too slow (OnPixelBased)
            'watermark' => new WatermarkDownloadProxyFilterLoader(
                $context,
                $this->imagine,
            ),
            'background_fill' => new BackgroundFillFilterLoader($this->imagine),
            'strip' => new StripFilterLoader(),
            'scale' => new ScaleFilterLoader(),
            'upscale' => new UpscaleFilterLoader(),
            'downscale' => new DownscaleFilterLoader(),
            'auto_rotate' => new AutoRotateFilterLoader(),
            'rotate' => new RotateFilterLoader(),
            'flip' => new FlipFilterLoader(),
            'interlace' => new InterlaceFilterLoader(),
            'resample' => new ResampleFilterLoader($this->imagine),
            'fixed' => new FixedFilterLoader(),
            'stamp' => new StampFilter(
                $context,
                $this->imagine,
                $this->templateResolver,
            ),
        ];
    }
}
