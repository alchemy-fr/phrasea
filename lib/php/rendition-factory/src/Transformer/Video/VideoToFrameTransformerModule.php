<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Filter\FrameQualityFilter;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\GifFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\JpegFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OutputFormatsDocumentation;
use Alchemy\RenditionFactory\Transformer\Video\Format\PngFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\TiffFormat;
use FFMpeg\Media\Video;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class VideoToFrameTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats,
        private ModuleOptionsResolver $optionsResolver,
        private OutputFormatsDocumentation $outputFormatsDocumentation,
    ) {
    }

    public static function getName(): string
    {
        return 'video_to_frame';
    }

    private static function getSupportedOutputFormats(): array
    {
        return [
            GifFormat::getFormat(),
            JpegFormat::getFormat(),
            PngFormat::getFormat(),
            TiffFormat::getFormat(),
        ];
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Extract one frame from the video.
            HEADER,
            $this->outputFormatsDocumentation->listFormats(self::getSupportedOutputFormats()),
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->scalarNode('start')
                        ->defaultValue(0)
                        ->info('Offset of frame in seconds or timecode')
                        ->example('2.5 ; "00:00:02.50" ; "{{ attr.start }}"')
                    ->end()
                    ->scalarNode('format')
                        ->isRequired()
                        ->info('output format')
                        ->example('image-jpeg')
                    ->end()
                    ->scalarNode('extension')
                        ->defaultValue('default extension from format')
                        ->info('extension of the output file')
                        ->example('jpg')
                    ->end()
                    ->scalarNode('quality')
                        ->info('Change the quality of the output file (0-100)')
                        ->defaultValue(80)
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
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Video !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be video');
        }

        $commonArgs = new ModuleCommonArgs($this->formats, self::getSupportedOutputFormats(), $options, $context, $this->optionsResolver);
        $outputFormat = $commonArgs->getOutputFormat();

        /** @var Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = $context->getTemplatingContext();
        $resolverContext['input'] = $video->getStreams()->videos()->first()->all();

        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);
        if (null === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start.');
        }
        $start = FFMpegHelper::timecodeToseconds($startAsTimecode);
        $context->log(sprintf('  start=%s (%.02f)', $startAsTimecode, $start));

        $frame = $video->frame($startAsTimecode);

        $quality = (int) $this->optionsResolver->resolveOption($options['quality'] ?? 80, $resolverContext);
        if ($quality < 0 || $quality > 100) {
            throw new \InvalidArgumentException('Invalid quality, must be 0...100.');
        }
        $frame->addFilter(new FrameQualityFilter($quality));

        $outputPath = $context->createTmpFilePath($commonArgs->getExtension());

        $frame->save($outputPath);

        unset($frame, $video);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false,
        );
    }
}
