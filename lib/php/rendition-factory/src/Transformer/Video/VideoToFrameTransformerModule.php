<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg\Media\Video;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class VideoToFrameTransformerModule extends AbstractVideoTransformer implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_to_frame';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('module')
                ->isRequired()
                ->defaultValue(self::getName())
                ->end()
            ->booleanNode('enabled')
                ->defaultTrue()
                ->info('Whether to enable this module')
                ->end()
            ->arrayNode('options')
                ->info('Options for the module')
                ->children()
                    ->scalarNode('start')
                        ->defaultValue(0)
                        ->info('Offset of frame in seconds or timecode')
                        ->example('2.5 ; "00:00:02.50" ; "{{ metadata.start }}"')
                        ->end()
                ->end()
            ->end()
        ;
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

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

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
