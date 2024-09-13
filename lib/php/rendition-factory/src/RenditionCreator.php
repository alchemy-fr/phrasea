<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RenditionCreator
{
    /** @var TransformationContext[] */
    private array $createdContexts = [];
    /** @var OutputFile[] */
    private array $createdOutputFiles = [];

    public function __construct(
        private readonly TransformationContextFactory $contextFactory,
        private readonly FileFamilyGuesser $fileFamilyGuesser,
        /** @var TransformerModuleInterface[] */
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceLocator $transformers,
    ) {
    }

    public function createRendition(
        string $src,
        string $mimeType,
        BuildConfig $buildConfig,
        ?CreateRenditionOptions $options = null
    ): OutputFileInterface
    {
        $inputFile = new InputFile($src, $mimeType, $this->fileFamilyGuesser->getFamily($mimeType), $options->getMetadataContainer());
        if (null == $familyBuildConfig = $buildConfig->getFamily($inputFile->getFamily())) {
            throw new \InvalidArgumentException(sprintf(
                'No build config defined for family "%s" (type: "%s")',
                $inputFile->getFamily()->value,
                $mimeType,
            ));
        }

        $transformations = $familyBuildConfig->getTransformations();
        if (empty($transformations)) {
            throw new \InvalidArgumentException(sprintf(
                'No transformation defined for family "%s" (type: "%s")',
                $inputFile->getFamily()->value,
                $mimeType,
            ));
        }

        $context = $this->contextFactory->create(
            $options
        );
        $this->createdContexts[] = $context;

        $transformationCount = count($transformations);
        foreach (array_values($transformations) as $i => $transformation) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformation->getModule());
            $outputFile = $transformer->transform($inputFile, $transformation->getOptions(), $context);
            $this->createdOutputFiles[] = $outputFile;

            if ($i < $transformationCount) {
                $inputFile = $outputFile->createNextInputFile();
            }
        }

        return $outputFile;
    }

    public function cleanUp(): void
    {
        foreach ($this->createdOutputFiles as $outputFile) {
            @unlink($outputFile->getPath());
        }
        foreach ($this->createdContexts as $context) {
            @rmdir($context->getWorkingDirectory());
        }
    }

    private function createOutputFromInput(InputFileInterface $inputFile): OutputFileInterface
    {
        return new OutputFile($inputFile->getPath(), $inputFile->getType(), $inputFile->getFamily());
    }
}
