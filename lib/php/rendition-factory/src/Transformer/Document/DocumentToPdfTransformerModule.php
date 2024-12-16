<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\PdfConverter;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DocumentToPdfTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'document_to_pdf';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Convert any document to pdf format.
            HEADER
        );
    }


    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if ('application/pdf' === $inputFile->getType()) {
            return $inputFile->createOutputFile();
        }

        $newPath = $context->createTmpFilePath('pdf');

        $pdfConvert = new PdfConverter();

        $pdfConvert->convert($inputFile->getPath(), $newPath);

        return new OutputFile(
            $newPath,
            'application/pdf',
            FamilyEnum::Document,
            false // TODO implement projection
        );
    }
}
