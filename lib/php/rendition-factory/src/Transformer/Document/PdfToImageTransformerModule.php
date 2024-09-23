<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;

final readonly class PdfToImageTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'pdf_to_image';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if ($inputFile->getType()!=='application/pdf') {
            // TODO normalize file to PDF
            throw new \InvalidArgumentException('Invalid input file');
        }

        $extension = $options['extension'] ?? 'jpeg';
        $pdf = new Pdf($inputFile->getPath());

        if(!$pdf->isValidOutputFormat($extension)) {
            throw new \InvalidArgumentException('Invalid extension option');
        }

        $resolution = $options['resolution'] ?? 300;
        $quality = $options['quality'] ?? 100;

        $newPath = $context->createTmpFilePath($extension);

        $pdf->format(OutputFormat::tryFrom($extension))
            ->resolution($resolution)
            ->quality($quality)
            ->save($newPath);

        return new OutputFile(
            $newPath,
            $context->guessMimeTypeFromPath($newPath),
            FamilyEnum::Image
        );
    }
}
