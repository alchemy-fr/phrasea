<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\AacFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\GifFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\JpegFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\MkvFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mp3Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mpeg4Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\MpegFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OgaFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OgvFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OutputFormatsDocumentation;
use Alchemy\RenditionFactory\Transformer\Video\Format\PngFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\QuicktimeFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\TiffFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WavFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WebmFormat;
use FFMpeg;
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
            // image
            GifFormat::getFormat(),
            JpegFormat::getFormat(),
            PngFormat::getFormat(),
            TiffFormat::getFormat(),
            // video
            MkvFormat::getFormat(),
            Mpeg4Format::getFormat(),
            MpegFormat::getFormat(),
            QuicktimeFormat::getFormat(),
            WebmFormat::getFormat(),
            OgvFormat::getFormat(),
            // audio
            AacFormat::getFormat(),
            WavFormat::getFormat(),
            Mp3Format::getFormat(),
            OgaFormat::getFormat(),
        ];
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
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
                    ->info('Clip the video or audio')
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
            'resample_audio' => function (ArrayNodeDefinition $root): void {
                $root
                    ->info('Resample the audio')
                    ->children()
                        ->scalarNode('name')->isRequired()->defaultValue('resample_audio')->end()
                        ->scalarNode('enabled')->defaultTrue()->end()
                        ->scalarNode('rate')->isRequired()->defaultValue('44100')->end()
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

        $commonArgs = new ModuleCommonArgs($this->formats, self::getSupportedOutputFormats(), $options, $context, $this->optionsResolver);

        if (FamilyEnum::Video === $inputFile->getFamily()) {
            $videoTransformer = new VideoTransformer($this->optionsResolver, $this->imagine);

            return $videoTransformer->doVideo($options, $inputFile, $context, $commonArgs);
        }

        if (FamilyEnum::Audio === $inputFile->getFamily()) {
            $audioTransformer = new AudioTransformer($this->optionsResolver);

            return $audioTransformer->doAudio($options, $inputFile, $context, $commonArgs);
        }

        throw new \InvalidArgumentException(sprintf('Invalid format %s, only video or audio format supported', $commonArgs->getOutputFormat()->getFormat()));
    }
}
