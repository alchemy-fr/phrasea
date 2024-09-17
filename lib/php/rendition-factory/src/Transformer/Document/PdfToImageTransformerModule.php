<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;

final readonly class PdfToImageTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'PdfToImage';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContext $context): OutputFileInterface
    {
        if ($inputFile->getType()!=='application/pdf') {
            throw new \InvalidArgumentException('Invalid input file');
        }

        $extension = $options['extension'] ?? 'jpeg';

        $newPath = $context->createTmpFilePath($extension);

        $pdf = new Pdf($inputFile->getPath());

        if(!$pdf->isValidOutputFormat($extension)) {
            throw new \InvalidArgumentException('Invalid extension option');
        }

        $return = $pdf->format(OutputFormat::tryFrom($extension))
            ->resolution(300)
            ->quality(100)
            ->save($newPath);

        return new OutputFile(
            $newPath,
            $context->guessMimeTypeFromPath($newPath),
            FamilyEnum::Image
        );
    }
}
