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
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\Artwork;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\GifFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\JpegFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OutputFormatsDocumentation;
use Alchemy\RenditionFactory\Transformer\Video\Format\PngFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\TiffFormat;
use FFMpeg\Media\Audio;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class AlbumArtworkTransformerModule implements TransformerModuleInterface
{
    public function __construct(
        #[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats,
        private ModuleOptionsResolver $optionsResolver,
        private OutputFormatsDocumentation $outputFormatsDocumentation,
    ) {
    }

    public static function getName(): string
    {
        return 'album_artwork';
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
            Extract the artwork (cover) of an audio file.
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
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Audio !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be audio');
        }

        $commonArgs = new ModuleCommonArgs($this->formats, self::getSupportedOutputFormats(), $options, $context, $this->optionsResolver);
        $outputFormat = $commonArgs->getOutputFormat();

        /** @var Audio $audio */
        $audio = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $outputPath = $context->createTmpFilePath($commonArgs->getExtension());

        // php-ffmpeg requires a "AudioInterface" output format; Artwork is a subclass of DefaultAudio
        $audio->save(new Artwork(), $outputPath);

        unset($audio);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false,
        );
    }
}
