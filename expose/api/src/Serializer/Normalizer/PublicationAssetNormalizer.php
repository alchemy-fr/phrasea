<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\PublicationAsset;

class PublicationAssetNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param PublicationAsset $object
     */
    public function normalize($object, array &$context = [])
    {
        $context['publication_asset'] = $object;
    }

    public function support($object, $format): bool
    {
        return $object instanceof PublicationAsset;
    }
}
