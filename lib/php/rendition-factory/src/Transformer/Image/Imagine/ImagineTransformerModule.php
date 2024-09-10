<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Liip\ImagineBundle\Model\FileBinary;

final readonly class ImagineTransformerModule implements TransformerModuleInterface
{
    public function __construct(
        private ImagineFilterFactory $filterFactory,
    )
    {
    }

    public static function getName(): string
    {
        return 'imagine';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContext $context): OutputFileInterface
    {
        $options['format'] ??= $context->getFormat($inputFile->getType());
        $options['filters'] = $this->normalizeFilters($options['filters'] ?? []);

        $filterManager = $this->filterFactory->createFilterManager($context);

        $image = new FileBinary($inputFile->getPath(), $inputFile->getType());
        $output = $filterManager->apply($image, $options);

        $outputPath = $context->createTmpFilePath($output->getFormat());
        file_put_contents($outputPath, $output->getContent());

        return new OutputFile(
            $outputPath,
            $output->getMimeType(),
            FamilyEnum::Image
        );
    }

    private function normalizeFilters(array $filters): array
    {
        foreach ($filters as $filter => $options) {
            $filters[$filter] = $options ?? [];
        }

        return $filters;
    }
}
