<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\Exception\UnsupportedSourceFileException;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Integration\Core\Rendition\AssetMetadataContainer;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\RenditionBuild\Exception\RenditionBuildException;
use App\Service\Metadata\AssetMetadataFileWriter;
use App\Service\Storage\FileManager;
use App\Service\Storage\RenditionManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RenditionBuilder
{
    public function __construct(
        private YamlLoader $loader,
        private RenditionBuildHashManager $buildHashManager,
        private RenditionManager $renditionManager,
        private EntityManagerInterface $em,
        private AttributesResolver $attributesResolver,
        private AssetNameResolver $assetNameResolver,
        private FileManager $fileManager,
        private RenditionCreator $renditionCreator,
        private FileFetcher $fileFetcher,
        private AssetMetadataFileWriter $assetMetadataFileWriter,
        private RenditionDefinitionParser $definitionParser,
        private StoryContactSheetInputResolver $contactSheetInputResolver,
        private StoryContactSheetScheduler $contactSheetScheduler,
    ) {
    }

    /**
     * @param int $attempt for a "generate" definition, how many times the build already waited for the story items
     */
    public function buildRendition(RenditionDefinition $renditionDefinition, Asset $asset, bool $force = false, int $attempt = 0): void
    {
        if (RenditionDefinition::BUILD_MODE_NONE === $renditionDefinition->getBuildMode()) {
            throw new RenditionBuildException(true, 'Rendition definition is not buildable');
        }

        $isProjection = true;
        if ($asset->getWorkspaceId() !== $renditionDefinition->getWorkspaceId()) {
            throw new \LogicException(sprintf('Asset "%s" and rendition definition "%s" are not in the same workspace', $asset->getId(), $renditionDefinition->getId()));
        }

        if ($this->isGenerateDefinition($renditionDefinition)) {
            $this->buildGeneratedRendition($renditionDefinition, $asset, $force, $attempt);

            return;
        }

        if (null !== $parentDefinition = $renditionDefinition->getParent()) {
            $parentRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $parentDefinition);
            if (null === $parentRendition) {
                throw new RenditionBuildException(false, sprintf('Parent rendition "%s" not found for asset "%s"', $parentDefinition->getName(), $asset->getId()));
            }

            if (false === $parentRendition->getProjection()) {
                $isProjection = false;
            }
            $source = $parentRendition->getFile();

            if (null === $source) {
                throw new \LogicException(sprintf('Parent rendition "%s" of Asset "%s" has no source file', $parentRendition->getId(), $asset->getId()));
            }
        } else {
            $source = $asset->getSource();
            if (null === $source) {
                throw new RenditionBuildException(false, sprintf('Asset "%s" has no source file', $asset->getId()));
            }
        }

        $buildHash = $this->buildHashManager->getBuildHash($source, $renditionDefinition);

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $renditionDefinition);
        if (!$force && null !== $existingRendition && $existingRendition->getBuildHash() === $buildHash) {
            return;
        }

        if (RenditionDefinition::BUILD_MODE_PICK_SOURCE === $renditionDefinition->getBuildMode()) {
            $this->renditionManager->createOrReplaceRenditionFile(
                $asset,
                $renditionDefinition,
                $source,
                null,
                null,
                projection: $isProjection,
            );
            $this->em->flush();

            return;
        }

        $buildDef = $renditionDefinition->getDefinition();
        if (empty($buildDef)) {
            throw new RenditionBuildException(true, 'Rendition definition is empty');
        }

        $metadataContainer = new AssetMetadataContainer($asset, $this->attributesResolver, $this->assetNameResolver);

        try {
            try {
                $outputFile = $this->createRendition($source, $buildDef, $metadataContainer);
            } catch (NoBuildConfigException|UnsupportedSourceFileException $e) {
                throw new RenditionBuildException(true, $e->getMessage(), $e->getCode(), $e);
            }

            $this->storeBuiltRendition($asset, $renditionDefinition, $outputFile ?? $source, $buildHash, $isProjection);
        } finally {
            $this->renditionCreator->cleanUp();
        }
    }

    /**
     * Whether the definition builds its rendition without source file ("generate:").
     */
    public function isGenerateDefinition(RenditionDefinition $renditionDefinition): bool
    {
        return $this->definitionParser->isGenerateDefinition($renditionDefinition);
    }

    /**
     * @return RenditionDefinition[]
     */
    public function getGenerateDefinitions(string $workspaceId): array
    {
        return array_values(array_filter(
            $this->renditionManager->getRenditionDefinitions($workspaceId),
            $this->isGenerateDefinition(...),
        ));
    }

    /**
     * A story contact sheet: assembles the already built renditions of the story items.
     */
    private function buildGeneratedRendition(RenditionDefinition $renditionDefinition, Asset $asset, bool $force, int $attempt): void
    {
        if (!$asset->isStory()) {
            throw new RenditionBuildException(true, 'not a story');
        }
        if (null !== $renditionDefinition->getParent()) {
            throw new RenditionBuildException(true, sprintf('Rendition definition "%s" generates its rendition: it cannot have a parent', $renditionDefinition->getName()));
        }

        /** @var BuildConfig $buildConfig */
        $buildConfig = $this->definitionParser->getBuildConfig($renditionDefinition);
        $input = $buildConfig->getGenerate()?->getInput() ?? StoryContactSheetInputResolver::INPUT_THUMBNAIL;
        if (!in_array($input, StoryContactSheetInputResolver::INPUTS, true)) {
            throw new RenditionBuildException(true, sprintf('Invalid generate input "%s", expected one of: %s', $input, implode(', ', StoryContactSheetInputResolver::INPUTS)));
        }

        try {
            $limit = $this->renditionCreator->getGeneratorInputCount($buildConfig);
        } catch (NoBuildConfigException $e) {
            throw new RenditionBuildException(true, $e->getMessage(), $e->getCode(), $e);
        }

        $inputs = $this->contactSheetInputResolver->resolve($asset, $input, $limit);
        if ($inputs->pending && $attempt < StoryContactSheetScheduler::MAX_ATTEMPTS) {
            // Rebuilt as soon as an item gets its rendition: the retry covers the builds that never come
            $this->contactSheetScheduler->scheduleRetry($asset->getId(), $attempt + 1);

            return;
        }

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $renditionDefinition);
        if (empty($inputs->files)) {
            if (null !== $existingRendition) {
                $this->renditionManager->removeRendition($existingRendition);
                $this->em->flush();
            }

            return;
        }

        $buildHash = $this->buildHashManager->getGenerateBuildHash($renditionDefinition, $inputs->files);
        if (!$force && null !== $existingRendition && $existingRendition->getBuildHash() === $buildHash) {
            return;
        }

        $metadataContainer = new AssetMetadataContainer($asset, $this->attributesResolver, $this->assetNameResolver);

        try {
            $inputFiles = array_map(fn (File $file): InputFile => new InputFile(
                $this->fileFetcher->getFile($file),
                $file->getType() ?? '',
                FamilyEnum::Image,
            ), $inputs->files);

            try {
                $outputFile = $this->renditionCreator->generateRendition($buildConfig, new CreateRenditionOptions(
                    metadataContainer: $metadataContainer,
                    inputFiles: $inputFiles,
                ));
            } catch (NoBuildConfigException|UnsupportedSourceFileException $e) {
                throw new RenditionBuildException(true, $e->getMessage(), $e->getCode(), $e);
            }

            $this->storeBuiltRendition($asset, $renditionDefinition, $outputFile, $buildHash, false);
        } finally {
            $this->renditionCreator->cleanUp();
        }
    }

    /**
     * @param OutputFileInterface|File $built the built file, or the source when the build left it untouched
     */
    private function storeBuiltRendition(
        Asset $asset,
        RenditionDefinition $renditionDefinition,
        OutputFileInterface|File $built,
        ?string $buildHash,
        bool $isProjection,
    ): void {
        if ($built instanceof OutputFileInterface) {
            if (!$built->isProjection()) {
                $isProjection = false;
            }

            $file = $this->fileManager->createFileFromPath(
                $asset->getWorkspace(),
                $built->getPath(),
                $built->getType()
            );
        } else {
            $file = $built;
        }

        $file->setNoAnalysisNeeded();

        // Building can take minutes: the asset may have been deleted meanwhile while
        // it is still held in the identity map. Check the database before writing
        // a rendition that would violate the asset FK (and close the EntityManager).
        if (!$this->assetStillExists($asset)) {
            throw new RenditionBuildException(true, sprintf('Asset "%s" was deleted while building rendition "%s"', $asset->getId(), $renditionDefinition->getName()));
        }

        $this->renditionManager->createOrReplaceRenditionFile(
            $asset,
            $renditionDefinition,
            $file,
            $buildHash,
            $built instanceof OutputFileInterface ? $built->getBuildHashes() : null,
            projection: $isProjection,
        );

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException $e) {
            throw new RenditionBuildException(true, sprintf('Asset "%s" was deleted while building rendition "%s"', $asset->getId(), $renditionDefinition->getName()), $e->getCode(), $e);
        }
    }

    public function buildDynamicRendition(AssetRendition $rendition): void
    {
        $asset = $rendition->getAsset();
        $buildDef = $rendition->getBuildDefinition();
        if (empty($buildDef)) {
            throw new RenditionBuildException(true, sprintf('Dynamic rendition "%s" has no build definition', $rendition->getId()));
        }

        $source = $this->resolveDynamicRenditionSource($rendition);
        $metadataContainer = new AssetMetadataContainer($asset, $this->attributesResolver, $this->assetNameResolver);

        try {
            try {
                $outputFile = $this->createRendition($source, $buildDef, $metadataContainer);
            } catch (NoBuildConfigException|UnsupportedSourceFileException $e) {
                throw new RenditionBuildException(true, $e->getMessage(), $e->getCode(), $e);
            }

            if (null !== $outputFile) {
                if ($rendition->isWriteMetadata()) {
                    $this->assetMetadataFileWriter->writeAssetMetadata($outputFile->getPath(), $asset, $rendition->getDefinition());
                }

                $file = $this->fileManager->createFileFromPath(
                    $asset->getWorkspace(),
                    $outputFile->getPath(),
                    $outputFile->getType()
                );
            } else {
                $file = $source;
            }

            $file->setNoAnalysisNeeded();

            $this->renditionManager->attachRenditionFile(
                $rendition,
                $file,
                $this->buildHashManager->getDynamicBuildHash($source, $rendition),
                $outputFile?->getBuildHashes(),
                $outputFile?->isProjection() ?? true,
            );
            $this->em->flush();
        } finally {
            $this->renditionCreator->cleanUp();
        }
    }

    private function resolveDynamicRenditionSource(AssetRendition $rendition): File
    {
        $asset = $rendition->getAsset();

        if (null !== $sourceRenditionId = $rendition->getSourceRenditionId()) {
            $sourceRendition = $this->em->find(AssetRendition::class, $sourceRenditionId);
            if (null === $sourceRendition || $sourceRendition->getAsset()->getId() !== $asset->getId()) {
                throw new RenditionBuildException(true, sprintf('Source rendition "%s" not found for asset "%s"', $sourceRenditionId, $asset->getId()));
            }

            $source = $sourceRendition->getFile();
            if (null === $source) {
                throw new RenditionBuildException(false, sprintf('Source rendition "%s" of asset "%s" has no file', $sourceRenditionId, $asset->getId()));
            }

            return $source;
        }

        $source = $asset->getSource();
        if (null === $source) {
            throw new RenditionBuildException(false, sprintf('Asset "%s" has no source file', $asset->getId()));
        }

        return $source;
    }

    private function createRendition(File $source, string $buildDef, AssetMetadataContainer $metadataContainer): ?OutputFileInterface
    {
        $sourcePath = $this->fileFetcher->getFile($source);

        $outputFile = $this->renditionCreator->createRendition(
            $sourcePath,
            $source->getType() ?? '',
            $this->loader->parse($buildDef),
            new CreateRenditionOptions(
                metadataContainer: $metadataContainer,
            )
        );

        if ($sourcePath === $outputFile->getPath()) {
            return null;
        }

        return $outputFile;
    }

    private function assetStillExists(Asset $asset): bool
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Asset::class, 'a')
            ->andWhere('a.id = :id')
            ->setParameter('id', $asset->getId())
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
