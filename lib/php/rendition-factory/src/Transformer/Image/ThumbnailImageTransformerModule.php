<?php

namespace Alchemy\RenditionFactory\Transformer\Image;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Imagine\Filter\Basic\Thumbnail;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

final readonly class ThumbnailImageTransformerModule implements TransformerModuleInterface
{
    public function __construct(
        private ImagineInterface $imagine,
    )
    {
    }

    public static function getName(): string
    {
        return 'thumbnail_image';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContext $context): OutputFileInterface
    {
        $image = $this->imagine->open($inputFile->getPath());

        $mode = ImageInterface::THUMBNAIL_OUTBOUND;
        if (!empty($options['mode']) && 'inset' === $options['mode']) {
            $mode = ImageInterface::THUMBNAIL_INSET;
        }

        if (!empty($options['filter'])) {
            $filter = \constant(ImageInterface::class.'::FILTER_'.strtoupper($options['filter']));
        }
        if (empty($filter)) {
            $filter = ImageInterface::FILTER_UNDEFINED;
        }

        $width = $options['width'] ?? null;
        $height = $options['height'] ?? $width;

        $size = $image->getSize();
        $origWidth = $size->getWidth();
        $origHeight = $size->getHeight();

        if (null === $width || null === $height) {
            if (null === $height) {
                $height = (int) (($width / $origWidth) * $origHeight);
            } elseif (null === $width) {
                $width = (int) (($height / $origHeight) * $origWidth);
            } else {
                throw new \InvalidArgumentException('"width" or "height" option must be defined');
            }
        }

        if (($origWidth > $width || $origHeight > $height)
            || (!empty($imagineOptions['allow_upscale']) && ($origWidth !== $width || $origHeight !== $height))
        ) {
            $filter = new Thumbnail(new Box($width, $height), $mode, $filter);
            $image = $filter->apply($image);
        }

        $imagineOptions = [
            'quality' => $options['quality'] ?? 100,
        ];

        if (isset($options['jpeg_quality'])) {
            $imagineOptions['jpeg_quality'] = $options['jpeg_quality'];
        }
        if (isset($options['png_compression_level'])) {
            $imagineOptions['png_compression_level'] = $options['png_compression_level'];
        }
        if (isset($options['png_compression_filter'])) {
            $imagineOptions['png_compression_filter'] = $options['png_compression_filter'];
        }

        if ('image/gif' === $inputFile->getType() && $options['animated']) {
            $imagineOptions['animated'] = $options['animated'];
        }

        $outputType = $options['format'] ?? $inputFile->getType();
        if (!str_starts_with($outputType, 'image/')) {
            $outputType = 'image/'.$outputType;
        }

        $outputExtension = match ($outputType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $newPath = $context->createTmpFilePath($outputExtension);
        $image->save($newPath, $imagineOptions);
        unset($image);

        return new OutputFile(
            $newPath,
            $outputType,
            FamilyEnum::Image
        );
    }
}
