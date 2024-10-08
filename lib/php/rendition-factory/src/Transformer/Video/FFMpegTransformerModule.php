<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Media\Clip;
use FFMpeg\Media\Video;

final readonly class FFMpegTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'ffmpeg';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if (!($format = $options['format'])) {
            throw new \InvalidArgumentException('Missing format');
        }
        if (!($extension = $options['extension'])) {
            throw new \InvalidArgumentException('Missing extension');
        }

        $fqcnFormat = 'FFMpeg\\Format\\Video\\'.$format;
        if (class_exists($fqcnFormat)) {
            return $this->doVideo($format, $extension, $inputFile, $options, $context);
        }
        $fqcnFormat = 'FFMpeg\\Format\\Audio\\'.$format;
        if (class_exists($fqcnFormat)) {
            return $this->doAudio($format, $extension, $inputFile, $options, $context);
        }

        throw new \InvalidArgumentException(sprintf('Invalid format %s', $format));
    }

    private function doVideo(string $format, string $extension, InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $fqcnFormat = 'FFMpeg\\Format\\Video\\'.$format;
        /** @var FormatInterface $ouputFormat */
        $ouputFormat = new $fqcnFormat();

        if ($videoCodec = $options['video_codec'] ?? null) {
            if (!in_array($videoCodec, $ouputFormat->getAvailableVideoCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
            }
            $ouputFormat->setVideoCodec($videoCodec);
        }
        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $ouputFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $ouputFormat->setAudioCodec($audioCodec);
        }
        if (null !== ($videoKilobitrate = $options['video_kilobitrate'] ?? null)) {
            if (!method_exists($ouputFormat, 'setKiloBitrate')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support video_kilobitrate', $format));
            }
            if (!is_int($videoKilobitrate)) {
                throw new \InvalidArgumentException('Invalid video kilobitrate');
            }
            $ouputFormat->setKiloBitrate($videoKilobitrate);
        }
        if (null !== ($audioKilobitrate = $options['audio_kilobitrate'] ?? null)) {
            if (!method_exists($ouputFormat, 'setAudioKiloBitrate')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support audio_kilobitrate', $format));
            }
            if (!is_int($audioKilobitrate)) {
                throw new \InvalidArgumentException('Invalid audio kilobitrate');
            }
            $ouputFormat->setAudioKiloBitrate($audioKilobitrate);
        }
        if (null !== ($passes = $options['passes'] ?? null)) {
            if (!method_exists($ouputFormat, 'setPasses')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support passes', $format));
            }
            if (!is_int($passes) || $passes < 1) {
                throw new \InvalidArgumentException('Invalid passes count');
            }
            if (0 === $videoKilobitrate) {
                throw new \InvalidArgumentException('passes must not be set if video_kilobitrate is 0');
            }
            $ouputFormat->setPasses($passes);
        }

        $ffmpegOptions = [];
        if ($timeout = $options['timeout'] ?? null) {
            if (!is_int($timeout)) {
                throw new \InvalidArgumentException('Invalid timeout');
            }
            $ffmpegOptions['timeout'] = $timeout;
        }
        if ($threads = $options['threads'] ?? null) {
            if (!is_int($threads) || $threads < 1) {
                throw new \InvalidArgumentException('Invalid threads count');
            }
            $ffmpegOptions['ffmpeg.threads'] = $threads;
        }
        $ffmpeg = FFMpeg\FFMpeg::create($ffmpegOptions, $context->getLogger());

        /** @var Video $video */
        $video = $ffmpeg->open($inputFile->getPath());

        $filters = $options['filters'] ?? [];
        // first, turn the video into a clip
        if (!empty($filters) && 'pre_clip' === $filters[0]['name']) {
            $filter = array_shift($filters);
            $context->log(sprintf('Applying filter: %s', $filter['name']));
            $clip = $this->preClip($video, $filter, $context);
        } else {
            $clip = $video->clip(TimeCode::fromSeconds(0), TimeCode::fromString('01:00:00:00.00'));
        }

        foreach ($filters as $filter) {
            if ('pre_clip' === $filter['name']) {
                throw new \InvalidArgumentException('"pre_clip" filter must be the first filter');
            }
            if (!method_exists($this, $filter['name'])) {
                throw new \InvalidArgumentException(sprintf('Invalid filter: %s', $filter['name']));
            }
            $context->log(sprintf('Applying filter: %s', $filter['name']));

            /* @uses self::resize(), self::rotate(), self::pad(), self::crop(), self::clip(), self::synchronize()
             *  @uses self::watermark(), self::framerate(), self::remove_audio()
             */
            call_user_func([$this, $filter['name']], $clip, $filter, $context);
        }

        $outputPath = $context->createTmpFilePath($extension);

        $clip->save($ouputFormat, $outputPath);

        unset($clip, $video);
        gc_collect_cycles();

        $mimeType = $context->guessMimeTypeFromPath($outputPath);

        $fileFamilyGuesser = new FileFamilyGuesser();
        $family = $fileFamilyGuesser->getFamily($outputPath, $mimeType);

        return new OutputFile(
            $outputPath,
            $mimeType,
            $family
        );
    }

    /**
     * todo: implement audio filters.
     */
    private function doAudio(string $format, string $extension, InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $fqcnFormat = 'FFMpeg\\Format\\Audio\\'.$format;
        /** @var FormatInterface $ouputFormat */
        $ouputFormat = new $fqcnFormat();

        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $ouputFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $ouputFormat->setAudioCodec($audioCodec);
        }

        throw new \InvalidArgumentException('Audio transformation not implemented');
    }

    private function preClip(Video $video, array $options, TransformationContextInterface $context): Clip
    {
        $start = $options['start'] ?? 0;
        $duration = $options['duration'] ?? null;

        $startAsTimecode = $durationAsTimecode = false;
        if (is_string($start)) {
            $startAsTimecode = TimeCode::fromString($start);
        } elseif (is_int($start) && $start >= 0) {
            $startAsTimecode = TimeCode::fromSeconds($start);
        }
        if (false === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }

        if (is_string($duration)) {
            $durationAsTimecode = TimeCode::fromString($duration);
        } elseif (is_int($duration) && $duration > 0) {
            $durationAsTimecode = TimeCode::fromSeconds($duration);
        }
        if (false === $durationAsTimecode) {
            throw new \InvalidArgumentException('Invalid duration for filter "clip"');
        }

        return $video->clip($startAsTimecode, $durationAsTimecode);
    }

    private function remove_audio(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $customFilter = '-an';
        $clip->addFilter(new FFMpeg\Filters\Audio\SimpleFilter([$customFilter]));
    }

    private function resize(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $dimension = $this->getDimension($options, 'resize');
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
            throw new \InvalidArgumentException('Invalid mode for filter "resize"');
        }

        $clip->filters()->resize(
            $dimension,
            $mode
        );
    }

    private function rotate(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        static $rotations = [
            90 => FFMpeg\Filters\Video\RotateFilter::ROTATE_90,
            180 => FFMpeg\Filters\Video\RotateFilter::ROTATE_180,
            270 => FFMpeg\Filters\Video\RotateFilter::ROTATE_270,
        ];
        $angle = $options['angle'] ?? 0;
        if (!array_key_exists($angle, $rotations)) {
            throw new \InvalidArgumentException('Invalid rotation, must be 90, 180 or 270 for filter "rotate"');
        }

        $clip->filters()->rotate($rotations[$angle]);
    }

    private function pad(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $dimension = $this->getDimension($options, 'pad');

        $clip->filters()->pad($dimension);
    }

    private function crop(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $point = new FFMpeg\Coordinate\Point($options['x'] ?? 0, $options['y'] ?? 0);
        $dimension = $this->getDimension($options, 'crop');

        $clip->filters()->crop($point, $dimension);
    }

    private function clip(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $start = $options['start'] ?? 0;
        $duration = $options['duration'] ?? null;

        $startAsTimecode = $durationAsTimecode = false;
        if (is_string($start)) {
            $startAsTimecode = TimeCode::fromString($start);
        } elseif (is_int($start) && $start >= 0) {
            $startAsTimecode = TimeCode::fromSeconds($start);
        }
        if (false === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }

        if (is_string($duration)) {
            $durationAsTimecode = TimeCode::fromString($duration);
        } elseif (is_int($duration) && $duration > 0) {
            $durationAsTimecode = TimeCode::fromSeconds($duration);
        }
        if (false === $durationAsTimecode) {
            throw new \InvalidArgumentException('Invalid duration for filter "clip"');
        }

        $clip->filters()->clip($startAsTimecode, $durationAsTimecode);
    }

    private function synchronize(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $clip->filters()->synchronize();
    }

    private function watermark(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $path = $options['path'] ?? null;
        if (!file_exists($path)) {
            throw new \InvalidArgumentException('Watermark file for filter "watermark" not found');
        }
        $position = $options['position'] ?? 'absolute';
        if ('relative' == $position) {
            $coord = array_filter($options, fn ($k) => in_array($k, ['bottom', 'right', 'top', 'left']), ARRAY_FILTER_USE_KEY);
            if (array_key_exists('bottom', $coord) && array_key_exists('top', $coord)
                || array_key_exists('right', $coord) && array_key_exists('left', $coord)) {
                throw new \InvalidArgumentException('Invalid relative coordinates for filter "watermark", only one of top/bottom or left/right can be set');
            }
        // in wm filter, missing coord are set to 0
        } elseif ('absolute' == $position) {
            $coord = array_filter($options, fn ($k) => in_array($k, ['x', 'y']), ARRAY_FILTER_USE_KEY);
        } else {
            throw new \InvalidArgumentException('Invalid position for filter "watermark"');
        }

        $clip->filters()->watermark($path, $coord);
    }

    private function framerate(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $framerate = $options['framerate'] ?? 0;
        if ($framerate <= 0) {
            throw new \InvalidArgumentException('Invalid framerate for filter "framerate"');
        }
        $gop = $options['gop'] ?? 0;

        $clip->filters()->framerate(new FFMpeg\Coordinate\FrameRate($framerate), $gop);
    }

    private function getDimension(array $options, string $filterName): FFMpeg\Coordinate\Dimension
    {
        $width = $options['width'] ?? 0;
        $height = $options['height'] ?? 0;
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid width/height for filter "%s"', $filterName));
        }

        return new FFMpeg\Coordinate\Dimension($width, $height);
    }
}
