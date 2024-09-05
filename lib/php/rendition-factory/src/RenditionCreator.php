<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory;

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

readonly class RenditionCreator
{
    private string $workingDirectory;

    public function __construct(
        private FileFamilyGuesser $fileFamilyGuesser,
        /** @var TransformerModuleInterface[] */
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $transformers,
        ?string $workingDirectory = null,
    ) {
        $this->workingDirectory = $workingDirectory ?? sys_get_temp_dir();
    }

    public function createRendition(
        string $src,
        string $mimeType,
        BuildConfig $buildConfig,
        ?CreateRenditionOptions $options = null
    ): OutputFileInterface
    {
        $inputFile = new InputFile($src, $mimeType, $this->fileFamilyGuesser->getFamily($mimeType));
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

        $dateWorkingDir = ($options?->getWorkingDirectory() ?? $this->workingDirectory).'/'.date('Y-m-d');
        if (!is_dir($dateWorkingDir)) {
            mkdir($dateWorkingDir);
        }
        $workingDir = $dateWorkingDir.'/'.uniqid();
        if (!is_dir($workingDir)) {
            mkdir($workingDir);
        }
        $context = new TransformationContext($workingDir);

        $transformationCount = count($transformations);
        foreach (array_values($transformations) as $i => $transformation) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformation->getModule());
            $outputFile = $transformer->transform($inputFile, $transformation->getOptions(), $context);

            if ($i < $transformationCount) {
                $inputFile = $outputFile->createNextInputFile();
            }
        }

        return $outputFile;
    }

    private function createOutputFromInput(InputFileInterface $inputFile): OutputFileInterface
    {
        return new OutputFile($inputFile->getPath(), $inputFile->getType(), $inputFile->getFamily());
    }
}
