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
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class VideoToAnimationTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats)
    {
    }

    public static function getName(): string
    {
        return 'video_to_animation';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if (!($format = $options['format'] ?? null)) {
            throw new \InvalidArgumentException('Missing format');
        }

        if (!$this->formats->has($format)) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s', $format));
        }
        /** @var FormatInterface $outputFormat */
        $outputFormat = $this->formats->get($format);
        if (FamilyEnum::Animation !== $outputFormat->getFamily()) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s, only animation formats supported', $format));
        }

        if (null != ($extension = $options['extension'] ?? null)) {
            if (!in_array($extension, $outputFormat->getAllowedExtensions())) {
                throw new \InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        } else {
            $extension = $outputFormat->getAllowedExtensions()[0];
        }

        $fromSeconds = FFMpeg\Coordinate\TimeCode::fromSeconds($options['from_seconds'] ?? 0);

        $duration = $options['duration'] ?? null;
        if (null !== $duration && ($duration = (int) $duration) <= 0) {
            throw new \InvalidArgumentException('Invalid duration');
        }

        if (($fps = (int) ($options['fps'] ?? 1)) <= 0) {
            throw new \InvalidArgumentException('Invalid fps');
        }

        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;
        if ((null !== $width && ($width = (int) $width) <= 0) || (null !== $height && ($height = (int) $height) <= 0)) {
            throw new \InvalidArgumentException('Invalid width or height');
        }

        $mode = $options['mode'] ?? FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET;
        if (!in_array(
            $mode,
            [
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_FIT,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_WIDTH,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_HEIGHT,
            ]
        )) {
            throw new \InvalidArgumentException('Invalid resize mode');
        }
        switch ($mode) {
            case FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET:
                list($width, $height) = $this->getDimensionsInset($inputFile->getPath(), $width, $height);
                break;
                // other modes not implemented
            default:
                throw new \InvalidArgumentException('Invalid resize mode');
        }

        $commands = [
            '-i',
            $inputFile->getPath(),
            '-ss',
            $fromSeconds,
        ];
        if (null !== $duration) {
            $commands[] = '-t';
            $commands[] = $duration;
        }
        $commands[] = '-vf';
        $commands[] = 'fps='.$fps.',scale='.$width.':'.$height.':flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse';

        $commands[] = '-loop';
        $commands[] = '0';

        $outputPath = $context->createTmpFilePath($extension);
        $commands[] = $outputPath;

        $ffmpeg = FFMpegHelper::createFFMpeg($options, $context);

        $ffmpeg->getFFMpegDriver()->command($commands);

        if (!file_exists($outputPath)) {
            throw new \RuntimeException('Failed to create animated gif');
        }

        unset($ffmpeg);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false // TODO implement projection
        );
    }

    private function getDimensionsInset($path, $width, $height): array
    {
        if (null === $width && null === $height) {
            return [-1, -1];
        }
        if (null === $width) {
            return [-1, $height];
        }
        if (null === $height) {
            return [$width, -1];
        }
        $ffmpeg = FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($path);
        $dimensions = null;
        foreach ($video->getStreams() as $stream) {
            if ($stream->isVideo()) {
                try {
                    $dimensions = $stream->getDimensions();
                    break;
                } catch (\Exception $e) {
                    // no-op
                }
            }
        }
        unset($video, $ffmpeg);
        if ($dimensions) {
            $wRatio = $width ? ($dimensions->getWidth() / $width) : 0;
            $hRatio = $height ? ($dimensions->getHeight() / $height) : 0;
            if ($wRatio > $hRatio) {
                return [(int) floor($dimensions->getWidth() / $wRatio), -1];
            } else {
                return [-1, (int) floor($dimensions->getHeight() / $hRatio)];
            }
        }

        // fallback : exact fit (might be not homothetic)
        return [$width, $height];
    }
}
