<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;

class AssetNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = [])
    {
        $object->setUrl($this->generateAssetUrl('asset_preview', $object));
        $object->setThumbUrl($this->generateAssetUrl('asset_thumbnail', $object));
        $object->setDownloadUrl($this->generateAssetUrl('asset_download', $object));
    }

    public function support($object, $format): bool
    {
        return $object instanceof Asset;
    }
}
