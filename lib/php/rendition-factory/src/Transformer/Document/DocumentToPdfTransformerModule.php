<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\UnsupportedSourceFileException;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\DocumentFormatDetector;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\PdfConverter;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
final readonly class DocumentToPdfTransformerModule implements TransformerModuleInterface
{
    public const string NAME = 'document_to_pdf';

    public function __construct(
        private PdfConverter $converter,
        private DocumentFormatDetector $formatDetector,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Convert a document to PDF with LibreOffice. A PDF is passed through as is.

            The format is told from the content of the file, never from its name or declared type.
            Accepted: Word (doc, docx), Excel (xls, xlsx), PowerPoint (ppt, pptx), OpenDocument
            (odt, ods, odp), RTF and plain text. Any other file (HTML, flat XML OpenDocument, SVG,
            images, macro-enabled Office files…) is refused as an unsupported source.
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->ignoreExtraKeys(false)
                ->children()
                    ->integerNode('timeout')
                        ->info(sprintf('Maximum duration of the conversion, in seconds (at most %d)', PdfConverter::MAX_TIMEOUT))
                        ->defaultValue(PdfConverter::DEFAULT_TIMEOUT)
                        ->min(1)
                        ->max(PdfConverter::MAX_TIMEOUT)
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $path = $inputFile->getPath();

        if ($this->formatDetector->isPdf($path)) {
            if ('application/pdf' === $inputFile->getType()) {
                return $inputFile->createOutputFile();
            }

            return new OutputFile($path, 'application/pdf', FamilyEnum::Document, true);
        }

        $format = $this->formatDetector->detect($path);
        if (null === $format) {
            throw new UnsupportedSourceFileException($path, $inputFile->getType());
        }

        $newPath = $context->createTmpFilePath('pdf');

        $this->converter->convert(
            $path,
            $format,
            $newPath,
            $context->createTmpFilePath(null),
            $this->getTimeout($options),
        );

        return new OutputFile(
            $newPath,
            'application/pdf',
            FamilyEnum::Document,
            false // TODO implement projection
        );
    }

    private function getTimeout(array $options): int
    {
        $timeout = $options['timeout'] ?? PdfConverter::DEFAULT_TIMEOUT;
        if (is_string($timeout) && ctype_digit($timeout)) {
            $timeout = (int) $timeout;
        }

        if (!is_int($timeout) || $timeout < 1 || $timeout > PdfConverter::MAX_TIMEOUT) {
            throw new \InvalidArgumentException(sprintf('Invalid timeout: an integer between 1 and %d is expected', PdfConverter::MAX_TIMEOUT));
        }

        return $timeout;
    }
}
