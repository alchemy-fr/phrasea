<?php

namespace App\Service\Asset;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Integration\Core\Rendition\AssetMetadataContainer;
use App\Service\Asset\Attribute\AssetTitleResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Storage\RenditionManager;

final readonly class RenditionBuildHashManager
{
    public function __construct(
        private RenditionCreator $renditionCreator,
        private AttributesResolver $attributesResolver,
        private AssetTitleResolver $assetTitleResolver,
        private RenditionManager $renditionManager,
        private YamlLoader $loader,
    ) {
    }

    public function getBuildHash(?File $source, RenditionDefinition $definition): ?string
    {
        if (RenditionDefinition::BUILD_MODE_CUSTOM !== $definition->getBuildMode()
            || null === $definition->getDefinition()) {
            return null;
        }

        return md5(implode('|', [
            $source?->getId() ?? 'no-source',
            $definition->getId(),
            $definition->getDefinition(),
        ]));
    }

    public function isRenditionDirty(AssetRendition $assetRendition): bool
    {
        $definition = $assetRendition->getDefinition();

        if (null !== $parentDefinition = $definition->getParent()) {
            $parentRendition = $this->renditionManager->getAssetRenditionByDefinition($assetRendition->getAsset(), $parentDefinition);
            if (null === $parentRendition) {
                return true;
            }

            $source = $parentRendition->getFile();
        } else {
            $source = $assetRendition->getAsset()->getSource();
        }

        if ($this->getBuildHash($source, $definition) !== $assetRendition->getBuildHash()) {
            return true;
        }

        if (!empty($moduleHashes = $assetRendition->getModuleHashes())) {
            return $this->renditionCreator->buildHashesDiffer(
                $moduleHashes,
                $this->loader->parse($definition->getDefinition()),
                new CreateRenditionOptions(
                    metadataContainer: new AssetMetadataContainer($assetRendition->getAsset(), $this->attributesResolver, $this->assetTitleResolver),
                ),
            );
        }

        return false;
    }
}
