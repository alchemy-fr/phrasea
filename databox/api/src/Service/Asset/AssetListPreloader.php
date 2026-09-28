<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Repository\Core\AssetRenditionRepository;
use App\Repository\Core\AttributeRepository;

/**
 * Warms the per-request caches needed to normalize a page of assets
 * (attributes, renditions, signed file URLs) with a handful of queries
 * instead of several per asset.
 */
final readonly class AssetListPreloader
{
    public function __construct(
        private AttributeRepository $attributeRepository,
        private AssetRenditionRepository $assetRenditionRepository,
        private FileUrlResolver $fileUrlResolver,
    ) {
    }

    /**
     * @param iterable<Asset> $assets
     */
    public function preload(iterable $assets): void
    {
        $assets = is_array($assets) ? $assets : iterator_to_array($assets, false);
        if (empty($assets)) {
            return;
        }

        $assetIds = array_map(fn (Asset $asset): string => $asset->getId(), $assets);

        $this->attributeRepository->prefetchAssetAttributes($assetIds);

        $renditionOptions = [AssetRenditionRepository::OPT_USED_AS => true];
        $this->assetRenditionRepository->prefetchAssetRenditions($assetIds, $renditionOptions);

        $files = [];
        foreach ($assets as $asset) {
            if (null !== $source = $asset->getSource()) {
                $files[$source->getId()] = $source;
            }
            foreach ($this->assetRenditionRepository->getCachedAssetRenditions($asset->getId(), $renditionOptions) as $rendition) {
                if (null !== $file = $rendition->getFile()) {
                    $files[$file->getId()] = $file;
                }
            }
        }

        $this->fileUrlResolver->preloadUrls(array_values(array_filter(
            $files,
            fn (File $file): bool => $file->isPathPublic()
        )));
    }
}
