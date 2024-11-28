<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\PdfConverter;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DocumentToPdfTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'document_to_pdf';
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
