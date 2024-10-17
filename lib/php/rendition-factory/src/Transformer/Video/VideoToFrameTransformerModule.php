<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\FormatInterface;
use FFMpeg;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class VideoToFrameTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats)
    {
    }

    public static function getName(): string
    {
        return 'video_to_frame';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if (!($format = $options['format'] ?? null)) {
            throw new InvalidArgumentException('Missing format');
        }

        if(!$this->formats->has($format)) {
            throw new InvalidArgumentException(sprintf('Invalid format %s', $format));
        }
        /** @var FormatInterface $outputFormat */
        $outputFormat = $this->formats->get($format);

        if($outputFormat->getFamily() !== FamilyEnum::Image) {
            throw new InvalidArgumentException(sprintf('Invalid format %s, only image formats supported', $format));
        }

        if (null != ($extension = $options['extension'] ?? null)) {
            if(!in_array($extension, $outputFormat->getAllowedExtensions())) {
                throw new InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        }
        else {
            $extension = ($outputFormat->getAllowedExtensions())[0];
        }

        $ffmpeg = FFMpeg\FFMpeg::create(); // (new FFMpeg\FFMpeg)->open('/path/to/video');

        $from_seconds = $options['from_seconds'] ?? 0;

        $video = $ffmpeg->open($inputFile->getPath());
        $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($from_seconds));
        $outputPath = $context->createTmpFilePath($extension);

        $frame->save($outputPath);

        unset($frame, $video, $ffmpeg);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
        );
    }
}
