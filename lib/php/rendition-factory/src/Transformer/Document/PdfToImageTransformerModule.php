<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class PdfToImageTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'pdf_to_image';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Convert the first page of a PDF to an image.
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->scalarNode('extension')
                        ->info('Output image extension: jpg, jpeg, png, or webp')
                        ->defaultValue('jpeg')
                    ->end()
                    ->integerNode('resolution')
                        ->info('Resolution of the output image in dpi')
                        ->defaultValue(300)
                    ->end()
                    ->integerNode('quality')
                        ->info('Quality of the output image, from 0 to 100')
                        ->defaultValue(100)
                    ->end()
                    ->arrayNode('size')
                        ->info('Size of the output image, [width, height] in pixels')
                        ->children()
                            ->integerNode(0)
                                ->info('Width of the output image in pixels')
                                ->example(150)
                            ->end()
                            ->integerNode(1)
                                ->info('Height of the output image in pixels')
                                ->example(100)
                            ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if ('application/pdf' !== $inputFile->getType()) {
            // TODO normalize file to PDF
            throw new \InvalidArgumentException('Invalid input file');
        }

        $extension = $options['extension'] ?? 'jpeg';
        $pdf = new Pdf($inputFile->getPath());

        if (!$pdf->isValidOutputFormat($extension)) {
            throw new \InvalidArgumentException('Invalid extension option');
        }

        $resolution = $options['resolution'] ?? 300;
        $quality = $options['quality'] ?? 100;
        $width = isset($options['size'][0]) ? $options['size'][0] : null;
        $height = isset($options['size'][1]) ? $options['size'][1] : null;

        $newPath = $context->createTmpFilePath($extension);

        $pdf->format(OutputFormat::tryFrom($extension))
            ->resolution($resolution)
            ->quality($quality);

        if (!empty($width) && !empty($height)) {
            $pdf->size($width, $height);
        }

        $pdf->save($newPath);

        return new OutputFile(
            $newPath,
            $context->guessMimeTypeFromPath($newPath),
            FamilyEnum::Image,
            false // TODO implement projection
        );
    }
}
