<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\MimeType\ImageFormatGuesser;
use Alchemy\RenditionFactory\Transformer\BuildHashDiffInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Liip\ImagineBundle\Model\FileBinary;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ImagineTransformerModule implements TransformerModuleInterface, BuildHashDiffInterface
{
    public function __construct(
        private ImagineFilterFactory $filterFactory,
    ) {
    }

    public static function getName(): string
    {
        return 'imagine';
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
        $inputFormat = ImageFormatGuesser::getFormat($inputFile->getType());
        if ('svg' === $inputFormat) {
            return $inputFile->createOutputFile();
        }

        $options['format'] ??= $inputFormat;
        $options['filters'] = $this->normalizeFilters($options['filters'] ?? []);

        $filterManager = $this->filterFactory->createFilterManager($context);

        $image = new FileBinary($inputFile->getPath(), $inputFile->getType());
        $output = $filterManager->apply($image, $options);

        $extension = $output->getFormat();
        if (empty($extension)) {
            $extension = $context->getExtension($output->getMimeType());
        }

        $outputPath = $context->createTmpFilePath($extension);
        file_put_contents($outputPath, $output->getContent());

        return new OutputFile(
            $outputPath,
            $output->getMimeType(),
            FamilyEnum::Image,
            false // TODO implement projection
        );
    }

    private function normalizeFilters(array $filters): array
    {
        foreach ($filters as $filter => $options) {
            $filters[$filter] = $options ?? [];
        }

        return $filters;
    }

    public function buildHashesDiffer(array $buildHashes, array $options, TransformationContextInterface $transformationContext): bool
    {
        $filterLoaders = $this->filterFactory->createFilterLoaders($transformationContext);

        if (!empty($buildHashes)) {
            $filterName = array_shift($buildHashes);

            $filters = $this->normalizeFilters($options['filters'] ?? []);
            if (!isset($filters[$filterName])) {
                return true;
            }

            $filter = $filterLoaders[$filterName] ?? null;
            if (!$filter instanceof BuildHashDiffInterface) {
                return true;
            }
            if ($filter->buildHashesDiffer($buildHashes, $filters[$filterName], $transformationContext)) {
                return true;
            }
        }

        return false;
    }
}
