<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Context\BuildHashes;
use Alchemy\RenditionFactory\Context\TransformationContext;
use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\Transformation;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\Transformer\BuildHashDiffInterface;
use Alchemy\RenditionFactory\Transformer\GeneratorModuleInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RenditionCreator
{
    /** @var TransformationContext[] */
    private array $createdContexts = [];

    public function __construct(
        private readonly TransformationContextFactory $contextFactory,
        private readonly FileFamilyGuesser $fileFamilyGuesser,
        /** @var TransformerModuleInterface[] */
        #[AutowireLocator(TransformerModuleInterface::TAG)]
        private readonly ServiceLocator $transformers,
        /** @var GeneratorModuleInterface[] */
        #[AutowireLocator(GeneratorModuleInterface::TAG)]
        private readonly ServiceLocator $generators,
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

        $transformations = $familyBuildConfig->getEnabledTransformations();
        if (empty($transformations)) {
            NoBuildConfigException::throwNoTransformation($inputFile->getFamily()->value, $mimeType);
        }

        $context = $this->contextFactory->create(
            $options
        );
        $this->createdContexts[] = $context;

        $buildHashes = $context->getBuildHashes();
        $buildHashes->setPath(BuildHashes::PATH_LEVEL_FAMILY, $inputFile->getFamily()->value);

        return $this->applyTransformations($inputFile, array_values($transformations), $context, 0, true);
    }

    /**
     * Builds a rendition without any source file, from the input files of the options.
     */
    public function generateRendition(
        BuildConfig $buildConfig,
        ?CreateRenditionOptions $options = null,
    ): OutputFileInterface {
        [$generator, $generatorTransformation, $transformations] = $this->getGenerateModules($buildConfig);

        $context = $this->contextFactory->create(
            $options
        );
        $this->createdContexts[] = $context;

        $buildHashes = $context->getBuildHashes();
        $buildHashes->setPath(BuildHashes::PATH_LEVEL_FAMILY, BuildConfig::GENERATE);
        $buildHashes->setPath(BuildHashes::PATH_LEVEL_MODULE, 0);

        $outputFile = $generator->generate($generatorTransformation->getOptions(), $context);
        if (empty($transformations)) {
            return $outputFile->withBuildHashes($buildHashes->getHashes());
        }

        return $this->applyTransformations(
            $outputFile->createNextInputFile(),
            $transformations,
            $context,
            1,
            $outputFile->isProjection(),
        );
    }

    /**
     * @return int how many input files the generator of a "generate" build uses at most
     */
    public function getGeneratorInputCount(BuildConfig $buildConfig): int
    {
        [$generator, $generatorTransformation] = $this->getGenerateModules($buildConfig);

        return $generator->getInputCount($generatorTransformation->getOptions());
    }

    public function isGenerator(string $moduleName): bool
    {
        return $this->generators->has($moduleName);
    }

    /**
     * @return array{0: GeneratorModuleInterface, 1: Transformation, 2: Transformation[]}
     */
    private function getGenerateModules(BuildConfig $buildConfig): array
    {
        if (null === $generateConfig = $buildConfig->getGenerate()) {
            throw new NoBuildConfigException(sprintf('No "%s" build config defined', BuildConfig::GENERATE));
        }

        $transformations = $generateConfig->getEnabledTransformations();
        if (empty($transformations)) {
            throw new NoBuildConfigException(sprintf('No transformation defined in "%s"', BuildConfig::GENERATE));
        }

        $generatorTransformation = array_shift($transformations);
        if (!$this->generators->has($generatorTransformation->getModule())) {
            throw new NoBuildConfigException(sprintf('The first module of "%s" must be a generator, "%s" is not', BuildConfig::GENERATE, $generatorTransformation->getModule()));
        }

        return [
            $this->generators->get($generatorTransformation->getModule()),
            $generatorTransformation,
            $transformations,
        ];
    }

    /**
     * @param Transformation[] $transformations indexed from 0
     * @param int              $moduleOffset    the build hashes module level of the first transformation
     */
    private function applyTransformations(
        InputFileInterface $inputFile,
        array $transformations,
        TransformationContextInterface $context,
        int $moduleOffset,
        bool $isProjection,
    ): OutputFileInterface {
        $buildHashes = $context->getBuildHashes();

        $outputFile = null;
        foreach ($transformations as $i => $transformation) {
            $buildHashes->setPath(BuildHashes::PATH_LEVEL_MODULE, $moduleOffset + $i);
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformation->getModule());
            $outputFile = $transformer->transform($inputFile, $transformation->getOptions(), $context);
            if (!$outputFile->isProjection()) {
                $isProjection = false;
            }

            $inputFile = $outputFile->createNextInputFile();
        }

        return $outputFile
            ->withBuildHashes($buildHashes->getHashes())
            ->withProjection($isProjection);
    }

    /**
     * Sample values:
     *   ["family", "module", "filter", "hash"],
     *   ["family", "module", "hash"]
     *
     * "family" is BuildConfig::GENERATE for a "generate" build, whose module 0 is the generator.
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
            $moduleOffset = array_shift($buildHash);

            if (BuildConfig::GENERATE === $family) {
                $transformations = $buildConfig->getGenerate()?->getEnabledTransformations();
                if (empty($transformations) || !isset($transformations[$moduleOffset])) {
                    return true;
                }

                $transformation = $transformations[$moduleOffset];
                $module = 0 === $moduleOffset
                    ? ($this->generators->has($transformation->getModule()) ? $this->generators->get($transformation->getModule()) : null)
                    : $this->transformers->get($transformation->getModule());
            } else {
                if (null === $familyEnum = FamilyEnum::tryFrom((string) $family)) {
                    return true;
                }

                $familyBuildConfig = $buildConfig->getFamily($familyEnum);
                if (null === $familyBuildConfig) {
                    return true;
                }

                $transformations = $familyBuildConfig->getEnabledTransformations();
                if (empty($transformations) || !isset($transformations[$moduleOffset])) {
                    return true;
                }

                $transformation = $transformations[$moduleOffset];
                $module = $this->transformers->get($transformation->getModule());
            }

            if (!$module instanceof BuildHashDiffInterface) {
                return true;
            }

            if ($module->buildHashesDiffer($buildHash, $transformation->getOptions(), $context)) {
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
