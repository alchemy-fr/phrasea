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
    public function normalize($object, array &$context = [])
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $context['publication_asset'] ?? null;
        if ($publicationAsset instanceof PublicationAsset) {
            $object->setUrl($this->generateAssetUrl('asset_preview', $publicationAsset));
            $object->setThumbUrl($this->generateAssetUrl('asset_thumbnail', $publicationAsset));
            $object->setDownloadUrl($this->generateAssetUrl('asset_download', $publicationAsset));
        }
    }

    public function support($object, $format): bool
    {
        return $object instanceof Asset;
    }
}
