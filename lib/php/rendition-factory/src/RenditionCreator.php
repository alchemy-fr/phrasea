<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Context\BuildHashes;
use Alchemy\RenditionFactory\Context\TransformationContext;
use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\Transformer\BuildHashDiffInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RenditionCreator
{
    /** @var TransformationContext[] */
    private array $createdContexts = [];

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
        ?CreateRenditionOptions $options = null,
    ): OutputFileInterface {
        $inputFile = new InputFile($src, $mimeType, $this->fileFamilyGuesser->getFamily($src, $mimeType));
        if (null == $familyBuildConfig = $buildConfig->getFamily($inputFile->getFamily())) {
            NoBuildConfigException::throwNoFamily($inputFile->getFamily()->value, $mimeType);
        }

        $transformations = $familyBuildConfig->getTransformations();
        if (empty($transformations)) {
            NoBuildConfigException::throwNoTransformation($inputFile->getFamily()->value, $mimeType);
        }

        $context = $this->contextFactory->create(
            $options
        );
        $this->createdContexts[] = $context;

        $buildHashes = $context->getBuildHashes();
        $buildHashes->setPath(BuildHashes::PATH_LEVEL_FAMILY, $inputFile->getFamily()->value);

        $transformationCount = count($transformations);
        foreach (array_values($transformations) as $i => $transformation) {
            $buildHashes->setPath(BuildHashes::PATH_LEVEL_MODULE, $i);
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformation->getModule());
            $outputFile = $transformer->transform($inputFile, $transformation->getOptions(), $context);

            if ($i < $transformationCount) {
                $inputFile = $outputFile->createNextInputFile();
            }
        }

        return $outputFile->withBuildHashes($buildHashes->getHashes());
    }

    /**
     * Sample values:
     *   ["family", "module", "filter", "hash"],
     *   ["family", "module", "hash"]
     */
    public function buildHashesDiffer(
        array $buildHashes,
        BuildConfig $buildConfig,
        ?CreateRenditionOptions $options = null,
    ): bool {
        $context = $this->contextFactory->createReadOnlyContext(
            $options
        );

        foreach ($buildHashes as $buildHash) {
            $family = array_shift($buildHash);
            $familyBuildConfig = $buildConfig->getFamily(FamilyEnum::from($family));
            if (null === $familyBuildConfig) {
                return true;
            }

            $transformations = $familyBuildConfig->getTransformations();
            if (empty($transformations)) {
                return true;
            }

            $moduleOffset = array_shift($buildHash);
            if (!isset($transformations[$moduleOffset])) {
                return true;
            }

            $transformation = $transformations[$moduleOffset];

            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformation->getModule());
            if (!$transformer instanceof BuildHashDiffInterface) {
                return true;
            }

            if ($transformer->buildHashesDiffer($buildHash, $transformation->getOptions(), $context)) {
                return true;
            }
        }

        return false;
    }

    public function cleanUp(): void
    {
        foreach ($this->createdContexts as $context) {
            self::recursiveRmDir($context->getWorkingDirectory());
        }

        $this->createdContexts = [];
    }

    private static function recursiveRmDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                self::recursiveRmDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
