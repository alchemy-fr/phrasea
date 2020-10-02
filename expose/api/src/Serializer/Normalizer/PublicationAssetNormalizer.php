<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Publication;
use App\Entity\PublicationAsset;

class PublicationAssetNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param PublicationAsset $object
     */
    public function normalize($object, array &$context = []): void
    {
        $context['publication_asset'] = $object;

        if (in_array(PublicationAsset::GROUP_READ, $context['groups'], true)) {
            if (!in_array(Publication::GROUP_READ, $context['groups'], true)) {
                $context['groups'][] = Publication::GROUP_READ;
            }
        }
    }

    public function support($object): bool
    {
        return $object instanceof PublicationAsset;
    }
}
