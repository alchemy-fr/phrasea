<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Filter\ResizeFilter;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\MkvFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mp3Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mpeg4Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\MpegFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OutputFormatsDocumentation;
use Alchemy\RenditionFactory\Transformer\Video\Format\QuicktimeFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WavFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WebmFormat;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\FormatInterface as FFMpegFormatInterface;
use FFMpeg\Media\Clip;
use FFMpeg\Media\Video;
use Imagine\Image\ImagineInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class FFMpegTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats,
        private ModuleOptionsResolver $optionsResolver,
        private ImagineInterface $imagine,
        private OutputFormatsDocumentation $outputFormatsDocumentation,
    ) {
    }

    public static function getName(): string
    {
        return 'ffmpeg';
    }

    private static function getSupportedOutputFormats(): array
    {
        return [
            MkvFormat::getFormat(),
            Mpeg4Format::getFormat(),
            MpegFormat::getFormat(),
            QuicktimeFormat::getFormat(),
            WebmFormat::getFormat(),
            WavFormat::getFormat(),
            Mp3Format::getFormat(),
        ];
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = Documentation::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());
        $doc = new Documentation(
            $treeBuilder,
            <<<HEADER
            apply filters to a video using FFMpeg.
            HEADER,
            $this->outputFormatsDocumentation->listFormats(self::getSupportedOutputFormats()).
            <<<FOOTER
            ### List of `ffmpeg` filters:
            FOOTER
        );

        foreach ($this->getExtraConfigurationBuilders() as $name => $builder) {
            $tree = new TreeBuilder('root');
            $builder($tree->getRootNode());
            $doc->addChild(new Documentation(
                $tree,
                <<<HEADER
                - `$name` filter
                HEADER
            ));
        }

        return $doc;
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->scalarNode('format')
                        ->info('output format')
                    ->end()
                    ->scalarNode('extension')
                        ->info('extension of the output file')
                    ->end()
                    ->scalarNode('video_codec')
                        ->info('Change the default video codec used by the output format')
                    ->end()
                    ->scalarNode('audio_codec')
                        ->info('Change the default audio codec used by the output format')
                    ->end()
                    ->scalarNode('video_kilobitrate')
                        ->info('Change the default video_kilobitrate used by the output format')
                    ->end()
                    ->scalarNode('audio_kilobitrate')
                        ->info('Change the default audio_kilobitrate used by the output format')
                    ->end()
                    ->scalarNode('passes')
                        ->defaultValue(2)
                        ->info('Change the number of ffmpeg passes')
                    ->end()
                    ->scalarNode('timeout')
                        ->info('Change the default timeout used by ffmpeg (defaults to symphony process timeout)')
                    ->end()
                    ->scalarNode('threads')
                        ->info('Change the default number of threads used by ffmpeg')
                    ->end()
                    ->arrayNode('filters')
                        ->info('Filters to apply to the video')
                        ->arrayPrototype()
                            ->info('see list of available filters below')
                            ->validate()->always()->then(function ($x) {
                                $this->validateFilter($x);
                            })->end()
                            ->children()
                                ->scalarNode('name')
                                    ->isRequired()
                                    ->info('Name of the filter')
                                ->end()
                                ->scalarNode('enabled')
                                    ->defaultTrue()
                                    ->info('Whether to enable the filter')
                                ->end()
                            ->end()
                            // false: (undocumented) ignore extra keys on general validation, but do NOT suppress (so the validate..then() can check them)
                            ->ignoreExtraKeys(false)
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on
    }

    private function validateFilter(array $filter): void
    {
        $name = $filter['name'];
        unset($filter['enabled']);
        if ($builder = $this->getExtraConfigurationBuilders()[$name] ?? null) {
            $tree = new TreeBuilder($name);
            $builder($tree->getRootNode());
            $processor = new Processor();
            $processor->process($tree->buildTree(), [$name => $filter]);
        } else {
            throw new InvalidConfigurationException(sprintf('Unknown filter: %s', $name));
        }
    }

    private function getExtraConfigurationBuilders(): iterable
    {
        static $configurations = [
            // @formatter:off
            'pre_clip' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Clip the video before applying other filters')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('pre_clip')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('start')
                            ->defaultValue(0)
                            ->info('Offset of frame in seconds or timecode')
                            ->example('2.5 ; "00:00:02.500" ; "{{ attr.start }}"')
                        ->end()
                        ->scalarNode('duration')
                            ->defaultValue(null)
                            ->info('Duration in seconds or timecode')
                            ->example('30 ; "00:00:30" ; "{{ input.duration/2 }}"')
                        ->end()
                    ->end();
            },
            'clip' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Clip the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('clip')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('start')
                            ->defaultValue(0)
                            ->info('Offset of frame in seconds or timecode')
                            ->example('2.5 ; "00:00:02.500" ; "{{ attr.start }}"')
                        ->end()
                        ->scalarNode('duration')
                            ->defaultValue(null)
                            ->info('Duration in seconds or timecode')
                            ->example('30 ; "00:00:30" ; "{{ input.duration/2 }}"')
                        ->end()
                    ->end();
            },
            'remove_audio' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Remove the audio from the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('remove_audio')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                    ->end();
            },
            'resize' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Resize the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('resize')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('width')
                            ->isRequired()
                            ->info('Width of the video')
                        ->end()
                        ->scalarNode('height')
                            ->isRequired()
                            ->info('Height of the video')
                        ->end()
                        ->scalarNode('mode')
                            ->defaultValue(FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET)
                            ->info('Resize mode')
                            ->example('inset')
                        ->end()
                            ->scalarNode('force_standards')
                            ->defaultValue(true)
                            ->info('Correct the width/height to the closest "standard" size')
                        ->end()
                    ->end();
            },
            'rotate' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Rotate the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('rotate')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('angle')
                            ->isRequired()
                            ->info('Angle of rotation [0 | 90 | 180 | 270]')
                            ->example('90')
                        ->end()
                    ->end();
            },
            'pad' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Pad the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('pad')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('width')
                            ->isRequired()
                            ->info('Width of the video')
                        ->end()
                        ->scalarNode('height')
                            ->isRequired()
                            ->info('Height of the video')
                        ->end()
                    ->end();
            },
            'crop' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Crop the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('crop')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('x')
                            ->isRequired()
                            ->info('X coordinate')
                        ->end()
                        ->scalarNode('y')
                            ->isRequired()
                            ->info('Y coordinate')
                        ->end()
                        ->scalarNode('width')
                            ->isRequired()
                            ->info('Width of the video')
                        ->end()
                        ->scalarNode('height')
                            ->isRequired()
                            ->info('Height of the video')
                        ->end()
                    ->end();
            },
            'watermark' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Apply a watermark on the video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('watermark')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('position')
                            ->isRequired()
                            ->info('"relative" or "absolute" position')
                        ->end()
                        ->scalarNode('path')
                            ->isRequired()
                            ->info('Path to the watermark image')
                        ->end()
                            ->scalarNode('top')
                            ->info('top coordinate (only if position is "relative", set top OR bottom)')
                        ->end()
                            ->scalarNode('bottom')
                            ->info('bottom coordinate (only if position is "relative", set top OR bottom)')
                        ->end()
                        ->scalarNode('left')
                            ->info('left coordinate (only if position is "relative", set left OR right)')
                        ->end()
                        ->scalarNode('right')
                            ->info('right coordinate (only if position is "relative", set left OR right)')
                        ->end()
                        ->scalarNode('x')
                            ->info('X coordinate (only if position is "absolute")')
                        ->end()
                        ->scalarNode('y')
                            ->info('Y coordinate (only if position is "absolute")')
                        ->end()
                    ->end();
            },
            'framerate' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Change the framerate')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('framerate')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('framerate')
                            ->isRequired()
                            ->info('framerate')
                        ->end()
                        ->scalarNode('gop')
                            ->info('gop')
                        ->end()
                    ->end();
            },
            'synchronize' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('re-synchronize audio and video')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('synchronize')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                    ->end();
            },
            // @formatter:on
        ];

        return $configurations;
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Video !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be video');
        }

        $commonArgs = new ModuleCommonArgs($this->formats, self::getSupportedOutputFormats(), $options, $context, $this->optionsResolver);

        if (FamilyEnum::Video === $commonArgs->getOutputFormat()->getFamily()) {
            return $this->doVideo($options, $inputFile, $context, $commonArgs);
        }

        if (FamilyEnum::Audio === $commonArgs->getOutputFormat()->getFamily()) {
            return $this->doVideo($options, $inputFile, $context, $commonArgs);
        }

        throw new \InvalidArgumentException(sprintf('Invalid format %s, only video or audio format supported', $commonArgs->getOutputFormat()->getFormat()));
    }

    private function doVideo(array $options, InputFileInterface $inputFile, TransformationContextInterface $transformationContext, ModuleCommonArgs $commonArgs): OutputFileInterface
    {
        $outputFormat = $commonArgs->getOutputFormat();
        $format = $outputFormat->getFormat();

        if (!method_exists($outputFormat, 'getFFMpegFormat')) {
            throw new \InvalidArgumentException(sprintf('format %s does not declare FFMpeg format', $format));
        }

        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $outputFormat->getFFMpegFormat();

        /** @var Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = $transformationContext->getTemplatingContext();
        $resolverContext['input'] = $video->getStreams()->videos()->first()->all();

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
             * @uses self::watermark(), self::framerate(), self::remove_audio()
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
    private function doAudio(array $options, InputFileInterface $inputFile, TransformationContextInterface $context, ModuleCommonArgs $commonArgs): OutputFileInterface
    {
        $resolverContext = $context->getTemplatingContext();

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
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);

        if (null === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "pre_clip"');
        }
        $start = FFMpegHelper::timecodeToseconds($startAsTimecode);
        if ($start > 0.0) {
            $isProjection = false;
        }

        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);
        if (null !== $duration) {
            $durationAsTimecode = FFMpegHelper::optionAsTimecode($duration);
            if (null === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "pre_clip"');
            }
            $isProjection = false;
            $transformationContext->log(sprintf("  Applying 'pre_clip' filter: start=%s (%.02f), duration=%s (%.02f)", $startAsTimecode, $start, $durationAsTimecode, $duration));
        } else {
            $durationAsTimecode = null;
            $transformationContext->log(sprintf("  Applying 'pre_clip' filter: start=%s (%.02f), duration=null", $startAsTimecode, $start));
        }

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
        $forceStandards = $this->optionsResolver->resolveOption($options['force_standards'] ?? true, $resolverContext);
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
        $clip->addFilter(new ResizeFilter($dimension, $mode, $forceStandards));

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
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);

        if (null === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }
        $start = FFMpegHelper::timecodeToseconds($startAsTimecode);
        if ($start > 0.0) {
            $isProjection = false;
        }

        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);
        if (null !== $duration) {
            $durationAsTimecode = FFMpegHelper::optionAsTimecode($duration);
            if (null === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "clip"');
            }
            $isProjection = false;
            $transformationContext->log(sprintf("  Applying 'clip' filter: start=%s (%.02f), duration=%s (%.02f)", $startAsTimecode, $start, $durationAsTimecode, $duration));
        } else {
            $durationAsTimecode = null;
            $transformationContext->log(sprintf("  Applying 'clip' filter: start=%s (%.02f), duration=null", $startAsTimecode, $start));
        }

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
        $path = $transformationContext->getRemoteFile($path);
        $wmImage = $this->imagine->open($path);
        $wmWidth = $wmImage->getSize()->getWidth();
        $wmHeight = $wmImage->getSize()->getHeight();
        unset($wmImage);

        $resolverContext['watermark'] = ['width' => $wmWidth, 'height' => $wmHeight];

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
        $coord['position'] = $position;

        $transformationContext->log(sprintf("  Applying 'watermark' filter: path=%s, coord=%s", $path, FFMpegHelper::coordAsText($coord)));
        $clip->filters()->watermark($path, $coord);
    }

    private function framerate(Clip $clip, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $framerate = (int) $this->optionsResolver->resolveOption($options['framerate'] ?? 0, $resolverContext);
        if ($framerate <= 0) {
            throw new \InvalidArgumentException('Invalid framerate for filter "framerate"');
        }
        $gop = (int) $this->optionsResolver->resolveOption($options['gop'] ?? 0, $resolverContext);

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
