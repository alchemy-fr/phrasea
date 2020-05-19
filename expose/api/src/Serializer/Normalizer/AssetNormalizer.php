<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\PublicationAsset;

class AssetNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $context['publication_asset'] ?? null;
        if ($publicationAsset instanceof PublicationAsset) {
            $asset = $publicationAsset->getAsset();

            $object->setUrl($this->generateAssetUrl($asset->getPreviewDefinition() ?? $asset));
            $object->setThumbUrl($this->generateAssetUrl($asset->getThumbnailDefinition() ?? $asset));
            $object->setDownloadUrl($this->generateAssetUrl($asset, true));
        } else {
            $object->setUrl($this->generateAssetUrl($object->getPreviewDefinition() ?? $object));
            $object->setThumbUrl($this->generateAssetUrl($object->getThumbnailDefinition() ?? $object));
            $object->setDownloadUrl($this->generateAssetUrl($object, true));
        }
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
