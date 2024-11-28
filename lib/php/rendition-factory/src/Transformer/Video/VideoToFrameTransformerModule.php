<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Format\FormatInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg\Media\Video;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class VideoToFrameTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats,
        private ModuleOptionsResolver $optionsResolver,
    ) {
    }

    public static function getName(): string
    {
        return 'video_to_frame';
    }

    public static function getDocumentation(): Documentation
    {
        static $doc = null;
        if (null === $doc) {
            $treeBuilder = Documentation::createBaseTree(self::getName());
            self::buildConfiguration($treeBuilder->getRootNode()->children());
            $doc = new Documentation(
                $treeBuilder,
                <<<HEADER
                Extract one frame from the video.
                HEADER
            );
        }

        return $doc;
    }

    private static function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->info('Options for the module')
                ->children()
                    ->scalarNode('start')
                        ->defaultValue(0)
                        ->info('Offset of frame in seconds or timecode')
                        ->example('2.5 ; "00:00:02.50" ; "{{ attr.start }}"')
                    ->end()
                    ->scalarNode('format')
                        ->isRequired()
                        ->info('Output format')
                        ->example('image-jpeg')
                    ->end()
                    ->scalarNode('extension')
                        ->defaultValue('default extension from format')
                        ->info('extension of the output file')
                        ->example('jpg')
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

        $commonArgs = new ModuleCommonArgs($this->formats, $options, $context, $this->optionsResolver);
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
