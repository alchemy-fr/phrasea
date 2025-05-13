<?php

namespace App\Asset;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Asset\Attribute\AssetTitleResolver;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\RenditionBuild\Exception\RenditionBuildException;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Integration\Core\Rendition\AssetMetadataContainer;
use App\Storage\FileManager;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RenditionBuilder
{
    public function __construct(
        private YamlLoader $loader,
        private RenditionBuildHashManager $buildHashManager,
        private RenditionManager $renditionManager,
        private EntityManagerInterface $em,
        private AttributesResolver $attributesResolver,
        private AssetTitleResolver $assetTitleResolver,
        private FileManager $fileManager,
        private RenditionCreator $renditionCreator,
        private FileFetcher $fileFetcher,
    ) {
    }

    public function buildRendition(RenditionDefinition $renditionDefinition, Asset $asset, bool $force = false): void
    {
        if (RenditionDefinition::BUILD_MODE_NONE === $renditionDefinition->getBuildMode()) {
            throw new RenditionBuildException(true, 'Rendition definition is not buildable');
        }

        $isProjection = true;
        if ($asset->getWorkspaceId() !== $renditionDefinition->getWorkspaceId()) {
            throw new \LogicException(sprintf('Asset "%s" and rendition definition "%s" are not in the same workspace', $asset->getId(), $renditionDefinition->getId()));
        }

        if (null !== $parentDefinition = $renditionDefinition->getParent()) {
            $parentRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $parentDefinition);
            if (null === $parentRendition) {
                throw new \LogicException(sprintf('Parent rendition "%s" not found for asset "%s"', $parentDefinition->getName(), $asset->getId()));
            }

            if (false === $parentRendition->getProjection()) {
                $isProjection = false;
            }
            $source = $parentRendition->getFile();
        } else {
            $source = $asset->getSource();
            if (null === $source) {
                throw new \LogicException(sprintf('No source file found for asset "%s"', $asset->getId()));
            }
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

        $buildHash = $this->buildHashManager->getBuildHash($source, $renditionDefinition);

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $renditionDefinition);
        if (!$force && $existingRendition?->getBuildHash() === $buildHash) {
            return;
        }

        $metadataContainer = new AssetMetadataContainer($asset, $this->attributesResolver, $this->assetTitleResolver);

        try {
            try {
                $outputFile = $this->createRendition($source, $buildDef, $metadataContainer);
            } catch (NoBuildConfigException $e) {
                throw new RenditionBuildException(true, $e->getMessage(), $e->getCode(), $e);
            }

            if (null !== $outputFile) {
                if (!$outputFile->isProjection()) {
                    $isProjection = false;
                }

                $file = $this->fileManager->createFileFromPath(
                    $asset->getWorkspace(),
                    $outputFile->getPath(),
                    $outputFile->getType()
                );
            } else {
                $file = $source;
            }

            $this->renditionManager->createOrReplaceRenditionFile(
                $asset,
                $renditionDefinition,
                $file,
                $buildHash,
                $outputFile?->getBuildHashes(),
                projection: $isProjection,
            );
            $this->em->flush();
        } finally {
            $this->renditionCreator->cleanUp();
        }
    }

    private function createRendition(File $source, string $buildDef, AssetMetadataContainer $metadataContainer): ?OutputFileInterface
    {
        $sourcePath = $this->fileFetcher->getFile($source);

        $outputFile = $this->renditionCreator->createRendition(
            $sourcePath,
            $source->getType(),
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
}
