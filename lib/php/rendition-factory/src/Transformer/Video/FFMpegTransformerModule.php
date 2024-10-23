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
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\FormatInterface as FFMpegFormatInterface;
use FFMpeg\Media\Clip;
use FFMpeg\Media\Video;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\TemplateWrapper;

final readonly class FFMpegTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats)
    {
    }

    public static function getName(): string
    {
        return 'ffmpeg';
    }

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    private function optionToTwig(&$option, TwigEnvironment $twig, array $context): void
    {
        if(is_array($option)) {
            foreach ($option as &$o) {
                $this->optionToTwig($o, $twig, $context);
            }
        }
        else {
            if(is_scalar($option)) {
                $option = $twig->createTemplate($option);
            }
            /** TemplateWrapper $option */
        }
    }

    /**
     * @throws SyntaxError
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LoaderError
     */
    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if (!($format = $options['format'])) {
            throw new InvalidArgumentException('Missing format');
        }

        if(!$this->formats->has($format)) {
            throw new InvalidArgumentException(sprintf('Invalid format %s', $format));
        }
        /** @var FormatInterface $outputFormat */
        $outputFormat = $this->formats->get($format);

        if (null != ($extension = $options['extension'] ?? null)) {
            if(!in_array($extension, $outputFormat->getAllowedExtensions())) {
                throw new InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        }
        else {
            $extension = ($outputFormat->getAllowedExtensions())[0];
        }

        if($outputFormat->getFamily() !== FamilyEnum::Video) {
            throw new InvalidArgumentException(sprintf('Invalid format %s, only video formats supported', $format));
        }

        if($outputFormat->getFamily() === FamilyEnum::Video) {
            return $this->doVideo($outputFormat, $extension, $inputFile, $options, $context);
        }

        if ($outputFormat->getFamily() === FamilyEnum::Audio) {
            return $this->doAudio($outputFormat, $extension, $inputFile, $options, $context);
        }

        throw new InvalidArgumentException(sprintf('Invalid format %s, only video or audio format supported', $format));
    }

    private function doVideo(FormatInterface $ouputFormat, string $extension, InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $format = $ouputFormat->getFormat();
        if(!method_exists($ouputFormat, 'getFFMpegFormat')) {
            throw new InvalidArgumentException('format %s does not declare FFMpeg format', $format);
        }

        $ffmpeg = FFMpegHelper::createFFMpeg($options, $context);

        /** @var Video $video */
        $video = $ffmpeg->open($inputFile->getPath());

        $twigContext = [
            'input' => $video->getStreams()->videos()->first()->all(),
            // 'metadata'  => $options-
        ];


 //       $this->optionToTwig($options, $context->getTwig(), $twigContext);




        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $ouputFormat->getFFMpegFormat();

        if ($videoCodec = $options['video_codec'] ?? null) {
            if (!in_array($videoCodec, $FFMpegFormat->getAvailableVideoCodecs())) {
                throw new InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
            }
            $FFMpegFormat->setVideoCodec($videoCodec);
        }
        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $FFMpegFormat->getAvailableAudioCodecs())) {
                throw new InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegFormat->setAudioCodec($audioCodec);
        }
        if (null !== ($videoKilobitrate = $options['video_kilobitrate'] ?? null)) {
            if (!method_exists($FFMpegFormat, 'setKiloBitrate')) {
                throw new InvalidArgumentException(sprintf('format %s does not support video_kilobitrate', $format));
            }
            if (!is_int($videoKilobitrate)) {
                throw new InvalidArgumentException('Invalid video kilobitrate');
            }
            $FFMpegFormat->setKiloBitrate($videoKilobitrate);
        }
        if (null !== ($audioKilobitrate = $options['audio_kilobitrate'] ?? null)) {
            if (!method_exists($FFMpegFormat, 'setAudioKiloBitrate')) {
                throw new InvalidArgumentException(sprintf('format %s does not support audio_kilobitrate', $format));
            }
            if (!is_int($audioKilobitrate)) {
                throw new InvalidArgumentException('Invalid audio kilobitrate');
            }
            $FFMpegFormat->setAudioKiloBitrate($audioKilobitrate);
        }
        if (null !== ($passes = $options['passes'] ?? null)) {
            if (!method_exists($FFMpegFormat, 'setPasses')) {
                throw new InvalidArgumentException(sprintf('format %s does not support passes', $format));
            }
            if (!is_int($passes) || $passes < 1) {
                throw new InvalidArgumentException('Invalid passes count');
            }
            if (0 === $videoKilobitrate) {
                throw new InvalidArgumentException('passes must not be set if video_kilobitrate is 0');
            }
            $FFMpegFormat->setPasses($passes);
        }

        $filters = $options['filters'] ?? [];

        // patch filters to transform relative "from" and "duration" to absolute values
        foreach($filters as &$f) {
            if (isset($f['from']) && str_ends_with(trim($f['from']), '%')) {
                if(null === $duration) {
                    throw new InvalidArgumentException('Unknown video duration, cannot use relative "from"');
                }
                $f['from'] = floor(($duration * (int)$f['from']) / 100);
            }
            if (isset($f['duration']) && str_ends_with(trim($f['duration']), '%')) {
                if(null === $duration) {
                    throw new InvalidArgumentException('Unknown video duration, cannot use relative "duration"');
                }
                $f['duration'] = floor(($duration * (int)$f['duration']) / 100);
            }
        }
var_dump($filters);
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
                throw new InvalidArgumentException('"pre_clip" filter must be the first filter');
            }
            if (!method_exists($this, $filter['name'])) {
                throw new InvalidArgumentException(sprintf('Invalid filter: %s', $filter['name']));
            }
            $context->log(sprintf('Applying filter: %s', $filter['name']));

            /* @uses self::resize(), self::rotate(), self::pad(), self::crop(), self::clip(), self::synchronize()
             *  @uses self::watermark(), self::framerate(), self::remove_audio()
             */
            call_user_func([$this, $filter['name']], $clip, $filter, $context);
        }

        $outputPath = $context->createTmpFilePath($extension);

        $clip->save($FFMpegFormat, $outputPath);

        unset($clip, $video, $ffmpeg);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $ouputFormat->getMimeType(),
            $ouputFormat->getFamily(),
            false // TODO implement projection
        );
    }

    /**
     * todo: implement audio filters.
     */
    private function doAudio(FormatInterface $ouputFormat, string $extension, InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $format = $ouputFormat->getFormat();
        if(!method_exists($ouputFormat, 'getFFMpegFormat')) {
            throw new InvalidArgumentException('format %s does not declare FFMpeg format', $format);
        }
        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $ouputFormat->getFFMpegFormat();

        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $FFMpegFormat->getAvailableAudioCodecs())) {
                throw new InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegFormat->setAudioCodec($audioCodec);
        }

        throw new InvalidArgumentException('Audio transformation not implemented');
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
            throw new InvalidArgumentException('Invalid start for filter "clip"');
        }

        if (is_string($duration)) {
            $durationAsTimecode = TimeCode::fromString($duration);
        } elseif (is_int($duration) && $duration > 0) {
            $durationAsTimecode = TimeCode::fromSeconds($duration);
        }
        if (false === $durationAsTimecode) {
            throw new InvalidArgumentException('Invalid duration for filter "clip"');
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
            throw new InvalidArgumentException('Invalid mode for filter "resize"');
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
            throw new InvalidArgumentException('Invalid rotation, must be 90, 180 or 270 for filter "rotate"');
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
            throw new InvalidArgumentException('Invalid start for filter "clip"');
        }

        if (is_string($duration)) {
            $durationAsTimecode = TimeCode::fromString($duration);
        } elseif (is_int($duration) && $duration > 0) {
            $durationAsTimecode = TimeCode::fromSeconds($duration);
        }
        if (false === $durationAsTimecode) {
            throw new InvalidArgumentException('Invalid duration for filter "clip"');
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
            throw new InvalidArgumentException('Watermark file for filter "watermark" not found');
        }
        $position = $options['position'] ?? 'absolute';
        if ('relative' == $position) {
            $coord = array_filter($options, fn ($k) => in_array($k, ['bottom', 'right', 'top', 'left']), ARRAY_FILTER_USE_KEY);
            if (array_key_exists('bottom', $coord) && array_key_exists('top', $coord)
                || array_key_exists('right', $coord) && array_key_exists('left', $coord)) {
                throw new InvalidArgumentException('Invalid relative coordinates for filter "watermark", only one of top/bottom or left/right can be set');
            }
        // in wm filter, missing coord are set to 0
        } elseif ('absolute' == $position) {
            $coord = array_filter($options, fn ($k) => in_array($k, ['x', 'y']), ARRAY_FILTER_USE_KEY);
        } else {
            throw new InvalidArgumentException('Invalid position for filter "watermark"');
        }

        $clip->filters()->watermark($path, $coord);
    }

    private function framerate(Clip $clip, array $options, TransformationContextInterface $context): void
    {
        $framerate = $options['framerate'] ?? 0;
        if ($framerate <= 0) {
            throw new InvalidArgumentException('Invalid framerate for filter "framerate"');
        }
        $gop = $options['gop'] ?? 0;

        $clip->filters()->framerate(new FFMpeg\Coordinate\FrameRate($framerate), $gop);
    }

    private function getDimension(array $options, string $filterName): FFMpeg\Coordinate\Dimension
    {
        $width = $options['width'] ?? 0;
        $height = $options['height'] ?? 0;
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid width/height for filter "%s"', $filterName));
        }

        return new FFMpeg\Coordinate\Dimension($width, $height);
    }
}
