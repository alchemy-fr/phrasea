<?php

namespace App\Asset;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Asset\Attribute\AssetTitleResolver;
use App\Asset\Attribute\AttributesResolver;
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
    )
    {
    }

    public function buildRendition(RenditionDefinition $renditionDefinition, Asset $asset, bool $force = false): void
    {
        if ($renditionDefinition->isPickSourceFile()) {
            $this->renditionManager->createOrReplaceRenditionFile($asset,
                $renditionDefinition,
                $asset->getSource(),
                null,
                null,
            );
            $this->em->flush();

            return;
        }

        if (null !== $parentDefinition = $renditionDefinition->getParent()) {
            $parentRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $parentDefinition);
            if (null === $parentRendition) {
                throw new \LogicException(sprintf('Parent rendition "%s" not found for asset "%s"', $parentDefinition->getName(), $asset->getId()));
            }

            $source = $parentRendition->getFile();
        } else {
            $source = $asset->getSource();
        }

        $buildHash = $this->buildHashManager->getBuildHash($source, $renditionDefinition);

        $existingRendition = $this->renditionManager->getAssetRenditionByDefinition($asset, $renditionDefinition);
        if (!$force && $existingRendition?->getBuildHash() === $buildHash) {
            return;
        }

        $metadataContainer = new AssetMetadataContainer($asset, $this->attributesResolver, $this->assetTitleResolver);

        try {
            $outputFile = $this->createRendition($source, $renditionDefinition->getDefinition(), $metadataContainer);

            if (null !== $outputFile) {
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
                $outputFile->getBuildHashes(),
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
