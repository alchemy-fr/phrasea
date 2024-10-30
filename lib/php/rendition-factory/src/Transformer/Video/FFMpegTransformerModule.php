<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\FormatInterface as FFMpegFormatInterface;
use FFMpeg\Media\Clip;
use FFMpeg\Media\Video;

final readonly class FFMpegTransformerModule extends VideoTransformerBase implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'ffmpeg';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Video !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be video');
        }

        $commonArgs = new ModuleCommonArgsDTO($this->formats, $options, $context, $this->optionsResolver);

        if (FamilyEnum::Video === $commonArgs->getOutputFormat()->getFamily()) {
            return $this->doVideo($options, $inputFile, $context, $commonArgs);
        }

        if (FamilyEnum::Audio === $commonArgs->getOutputFormat()->getFamily()) {
            return $this->doAudio($options, $inputFile, $context, $commonArgs);
        }

        throw new \InvalidArgumentException(sprintf('Invalid format %s, only video or audio format supported', $commonArgs->getOutputFormat()->getFormat()));
    }

    private function doVideo(array $options, InputFileInterface $inputFile, TransformationContextInterface $transformationContext, ModuleCommonArgsDTO $commonArgs): OutputFileInterface
    {
        $outputFormat = $commonArgs->getOutputFormat();
        $format = $outputFormat->getFormat();

        if (!method_exists($outputFormat, 'getFFMpegFormat')) {
            throw new \InvalidArgumentException('format %s does not declare FFMpeg format', $format);
        }

        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $outputFormat->getFFMpegFormat();

        /** @var Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = [
            'metadata' => $transformationContext->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

        if ($videoCodec = $this->optionsResolver->resolveOption($options['video_codec'] ?? null, $resolverContext)) {
            if (!in_array($videoCodec, $FFMpegFormat->getAvailableVideoCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
            }
            $FFMpegFormat->setVideoCodec($videoCodec);
        }
        if ($audioCodec = $this->optionsResolver->resolveOption($options['audio_codec'] ?? null, $resolverContext)) {
            if (!in_array($audioCodec, $FFMpegFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegFormat->setAudioCodec($audioCodec);
        }
        if (null !== ($videoKilobitrate = $this->optionsResolver->resolveOption($options['video_kilobitrate'] ?? null, $resolverContext))) {
            $videoKilobitrate = (int) $videoKilobitrate;
            if (!method_exists($FFMpegFormat, 'setKiloBitrate')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support video_kilobitrate', $format));
            }
            $FFMpegFormat->setKiloBitrate($videoKilobitrate);
        }
        if (null !== ($audioKilobitrate = $this->optionsResolver->resolveOption($options['audio_kilobitrate'] ?? null, $resolverContext))) {
            $audioKilobitrate = (int) $audioKilobitrate;
            if (!method_exists($FFMpegFormat, 'setAudioKiloBitrate')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support audio_kilobitrate', $format));
            }
            $FFMpegFormat->setAudioKiloBitrate($audioKilobitrate);
        }
        if (null !== ($passes = $this->optionsResolver->resolveOption($options['passes'] ?? null, $resolverContext))) {
            $passes = (int) $passes;
            if (!method_exists($FFMpegFormat, 'setPasses')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support passes', $format));
            }
            if ($passes < 1) {
                throw new \InvalidArgumentException('Invalid passes count');
            }
            if (0 === $videoKilobitrate) {
                throw new \InvalidArgumentException('passes must not be set if video_kilobitrate is 0');
            }
            $FFMpegFormat->setPasses($passes);
        }

        $filters = array_values(array_filter($options['filters'] ?? [],
            function ($filter) use ($resolverContext) {
                return $this->optionsResolver->resolveOption($filter['enabled'] ?? true, $resolverContext);
            }));

        $isProjection = true;

        // first, turn the video into a clip
        if (!empty($filters) && 'pre_clip' === $filters[0]['name']) {
            $filter = array_shift($filters);
            $clip = $this->preClip($video, $filter, $resolverContext, $transformationContext, $isProjection);
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

            /* @uses self::resize(), self::rotate(), self::pad(), self::crop(), self::clip(), self::synchronize()
             *  @uses self::watermark(), self::framerate(), self::remove_audio()
             */
            $this->{$filter['name']}($clip, $filter, $resolverContext, $transformationContext, $isProjection);
        }

        $outputPath = $transformationContext->createTmpFilePath($commonArgs->getExtension());

        $clip->save($FFMpegFormat, $outputPath);

        unset($clip, $video);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            $isProjection
        );
    }

    /**
     * todo: implement audio filters.
     */
    private function doAudio(array $options, InputFileInterface $inputFile, TransformationContextInterface $context, ModuleCommonArgsDTO $commonArgs): OutputFileInterface
    {
        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
        ];

        $format = $commonArgs->getOutputFormat()->getFormat();
        if (!method_exists($commonArgs->getOutputFormat(), 'getFFMpegFormat')) {
            throw new \InvalidArgumentException('format %s does not declare FFMpeg format', $format);
        }
        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $commonArgs->getOutputFormat()->getFFMpegFormat();

        if ($audioCodec = $this->optionsResolver->resolveOption($options['audio_codec'] ?? null, $resolverContext)) {
            if (!in_array($audioCodec, $FFMpegFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegFormat->setAudioCodec($audioCodec);
        }

        throw new \InvalidArgumentException('Audio transformation not implemented');
    }

    private function preClip(Video $video, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): Clip
    {
        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);

        $startAsTimecode = false;
        $durationAsTimecode = null;

        if (is_numeric($start) && (float) $start >= 0) {
            $startAsTimecode = TimeCode::fromSeconds($start);
        } elseif (is_string($start)) {
            $startAsTimecode = TimeCode::fromString($start);
        }
        if (false === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }
        if ($startAsTimecode->toSeconds() > 0) {
            $isProjection = false;
        }

        if (null !== $duration) {
            if (is_numeric($duration) && (float) $duration > 0) {
                $durationAsTimecode = TimeCode::fromSeconds($duration);
            } elseif (is_string($duration)) {
                $durationAsTimecode = TimeCode::fromString($duration);
            }
            if (false === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "pre_clip"');
            }
            $isProjection = false;
        }

        $transformationContext->log(sprintf("  Applying 'pre_clip' filter: start=%s, duration=%s", $startAsTimecode, $durationAsTimecode));

        return $video->clip($startAsTimecode, $durationAsTimecode);
    }

    private function remove_audio(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $customFilter = '-an';
        $transformationContext->log("  Applying 'remove_audio' filter");
        $clip->addFilter(new FFMpeg\Filters\Audio\SimpleFilter([$customFilter]));
    }

    private function resize(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $dimension = $this->getDimension($options, $resolverContext, 'resize');
        $mode = $this->optionsResolver->resolveOption($options['mode'] ?? FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, $resolverContext);
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

        $transformationContext->log(sprintf("  Applying 'resize' filter: dimension=[width=%s, height=%s], mode=%s", $dimension->getWidth(), $dimension->getHeight(), $mode));
        $clip->filters()->resize(
            $dimension,
            $mode
        );

        $isProjection = false;
    }

    private function rotate(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        static $rotations = [
            90 => FFMpeg\Filters\Video\RotateFilter::ROTATE_90,
            180 => FFMpeg\Filters\Video\RotateFilter::ROTATE_180,
            270 => FFMpeg\Filters\Video\RotateFilter::ROTATE_270,
        ];
        $angle = (int) $this->optionsResolver->resolveOption($options['angle'] ?? 0, $resolverContext);
        if (!array_key_exists($angle, $rotations)) {
            throw new \InvalidArgumentException('Invalid rotation, must be 90, 180 or 270 for filter "rotate"');
        }

        $transformationContext->log(sprintf("  Applying 'rotate' filter: angle=%d", $angle));
        $clip->filters()->rotate($rotations[$angle]);

        $isProjection = false;
    }

    private function pad(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $dimension = $this->getDimension($options, $resolverContext, 'pad');

        $transformationContext->log(sprintf("  Applying 'pad' filter: dimension=%s", FFMpegHelper::dimensionAsText($dimension)));
        $clip->filters()->pad($dimension);

        $isProjection = false;
    }

    private function crop(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $x = $this->optionsResolver->resolveOption($options['x'] ?? 0, $resolverContext);
        $y = $this->optionsResolver->resolveOption($options['y'] ?? 0, $resolverContext);
        if (!is_numeric($x) || !is_numeric($y)) {
            throw new \InvalidArgumentException('Invalid x/y for filter "crop"');
        }
        $point = new FFMpeg\Coordinate\Point((int) $x, (int) $y);
        $dimension = $this->getDimension($options, $resolverContext, 'crop');

        $transformationContext->log(sprintf("  Applying 'crop' filter: point=%s, dimension=%s", FFMpegHelper::pointAsText($point), FFMpegHelper::dimensionAsText($dimension)));
        $clip->filters()->crop($point, $dimension);

        $isProjection = false;
    }

    private function clip(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);

        $startAsTimecode = false;
        $durationAsTimecode = null;

        if (is_numeric($start) && (float) $start >= 0) {
            $startAsTimecode = TimeCode::fromSeconds($start);
        } elseif (is_string($start)) {
            $startAsTimecode = TimeCode::fromString($start);
        }
        if (false === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }
        if ($startAsTimecode->toSeconds() > 0) {
            $isProjection = false;
        }

        if (null !== $duration) {
            if (is_numeric($duration) && (float) $duration > 0) {
                $durationAsTimecode = TimeCode::fromSeconds($duration);
            } elseif (is_string($duration)) {
                $durationAsTimecode = TimeCode::fromString($duration);
            }
            if (false === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "pre_clip"');
            }
            $isProjection = false;
        }

        $transformationContext->log(sprintf("  Applying 'clip' filter: start=%s, duration=%s", $startAsTimecode, $durationAsTimecode));
        $clip->filters()->clip($startAsTimecode, $durationAsTimecode);
    }

    private function synchronize(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $transformationContext->log("  Applying 'synchronize' filter");
        $clip->filters()->synchronize();
    }

    private function watermark(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $path = $this->optionsResolver->resolveOption($options['path'] ?? null, $resolverContext);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException('Watermark file for filter "watermark" not found');
        }
        $position = $this->optionsResolver->resolveOption($options['position'] ?? 'absolute', $resolverContext);
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

        array_walk($coord, fn (&$v) => $v = (int) $this->optionsResolver->resolveOption($v, $resolverContext));

        $transformationContext->log(sprintf("  Applying 'watermark' filter: path=%s, coord=%s", $path, FFMpegHelper::coordAsText($coord)));
        $clip->filters()->watermark($path, $coord);
    }

    private function framerate(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $framerate = (int) $this->optionsResolver->resolveOption($options['framerate'] ?? 0, $resolverContext);
        if ($framerate <= 0) {
            throw new \InvalidArgumentException('Invalid framerate for filter "framerate"');
        }
        $gop = (int) ($options['gop'] ?? 0);

        $transformationContext->log(sprintf("  Applying 'framerate' filter: framerate=%d, gop=%d", $framerate, $gop));
        $clip->filters()->framerate(new FFMpeg\Coordinate\FrameRate($framerate), $gop);
    }

    private function getDimension(array $options, array $resolverContext, string $filterName): FFMpeg\Coordinate\Dimension
    {
        $width = (int) $this->optionsResolver->resolveOption($options['width'] ?? 0, $resolverContext);
        $height = (int) $this->optionsResolver->resolveOption($options['height'] ?? 0, $resolverContext);
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid width/height for filter "%s"', $filterName));
        }

        return new FFMpeg\Coordinate\Dimension($width, $height);
    }
}
