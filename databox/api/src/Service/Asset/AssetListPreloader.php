<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\File;
use App\Repository\Core\AssetRenditionRepository;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use App\Service\Image\ImageSetManager;
use Ramsey\Uuid\Uuid;

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
        private AttributeEntityRepository $attributeEntityRepository,
        private ImageSetManager $imageSetManager,
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
        $this->preloadEntityValues($assetIds);

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

    /**
     * The entity list values of the attributes, with their images.
     *
     * @param string[] $assetIds
     */
    private function preloadEntityValues(array $assetIds): void
    {
        $entityIds = [];
        foreach ($assetIds as $assetId) {
            foreach ($this->attributeRepository->getCachedAssetAttributes($assetId) as $attribute) {
                if (EntityAttributeType::NAME === $attribute->getDefinition()->getType() && Uuid::isValid($value = $attribute->getValue() ?? '')) {
                    $entityIds[$value] = true;
                }
            }
        }
        if (empty($entityIds)) {
            return;
        }

        $entities = DoctrineUtil::getFromIds($this->attributeEntityRepository, array_keys($entityIds));
        $this->imageSetManager->preload(array_map(fn (AttributeEntity $entity) => $entity->getImage(), $entities));
    }
}
