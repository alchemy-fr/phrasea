<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
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

    public static function getDocumentation(): Documentation
    {
        static $doc = null;
        if (null === $doc) {
            $treeBuilder = Documentation::createBaseTree(self::getName());
            self::buildConfiguration($treeBuilder->getRootNode()->children());
            $doc = new Documentation(
                $treeBuilder,
                <<<HEADER
                **documentation to be done**.
                HEADER
            );
        }

        return $doc;
    }

    private static function buildConfiguration(NodeBuilder $builder): void
    {
        // todo
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
