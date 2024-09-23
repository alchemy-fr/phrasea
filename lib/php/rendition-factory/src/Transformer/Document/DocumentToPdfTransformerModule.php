<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\PdfConverter;

final readonly class DocumentToPdfTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'document_to_pdf';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if ($inputFile->getType() === 'application/pdf') {
            return $inputFile->createOutputFile();
        }

        $newPath = $context->createTmpFilePath('pdf');

        $pdfConvert = new PdfConverter();

        $pdfConvert->convert($inputFile->getPath(), $newPath);

        return new OutputFile(
            $newPath,
            'application/pdf',
            FamilyEnum::Document
        );
    }
}
