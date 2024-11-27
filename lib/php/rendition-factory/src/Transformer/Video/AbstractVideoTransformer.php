<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\FormatInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

abstract readonly class AbstractVideoTransformer
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] protected ServiceLocator $formats,
        protected ModuleOptionsResolver $optionsResolver,
        protected ImagineInterface $imagine,
    ) {
    }

    public static function no_getDocumentationHeader(): ?string
    {
        return <<<_DOC_
        # Rendition Factory for video-input modules (wip)

        ## Common options

        ### `enabled` (optional)

                    Used to disable a whole module from the build chain.

            __default__: true

        ### `format` (mandatory)

        A format defines the output file :
        - family (image, video, audio, animation, document, unknown)
        - mime type (unique mime type for this type of file)
        - extension (possible extenstion(s) for this type of file)

        For a specific module, only a subset of formats may be available, e.g.:
        Since `video_to_frame` extracts one image from the video, the only supported output format(s)
        are ones of family=image.

        see below "Output formats" for the list of available formats.

        --------------------------------------------

        # Modules

        _DOC_;
    }

    public static function no_getDocumentationFooter(): ?string
    {
        return <<<_DOC_
        --------------------------------------------

        ## Output formats

        | format          | family    | mime type        | extension(s) |
        |-----------------|-----------|------------------|--------------|
        | animated-gif    | Animation | image/gif        | gif          |
        | animated-png    | Animation | image/png        | apng, png    |
        | animated-webp   | Animation | image/webp       | webp         |
        | image-jpeg      | Image     | image/jpeg       | jpg, jpeg    |
        | video-mkv       | Video     | video/x-matroska | mkv          |
        | video-mpeg4     | Video     | video/mp4        | mp4          |
        | video-mpeg      | Video     | video/mpeg       | mpeg         |
        | video-quicktime | Video     | video/quicktime  | mov          |
        | video-webm      | Video     | video/webm       | webm         |

        --------------------------------------------

        ## Resize modes
        ### `inset`
        The output is garanteed to fit in the requested size (width, height) and the aspect ratio is kept.
        - If only one dimension is provided, the other is computed.
        - If both dimensions are provided, the output is resize so the biggest dimension fits into the rectangle.
        - If no dimension is provided, the output is the same size as the input.

        --------------------------------------------

        ## twig context
        input.width

        input.height

        input.duration

        _DOC_;
    }
}
