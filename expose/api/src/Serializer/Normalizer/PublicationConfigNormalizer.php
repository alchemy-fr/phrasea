<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\PublicationConfig;

class PublicationConfigNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param PublicationConfig $object
     */
    public function normalize($object, array &$context = [])
    {
        if ($object->getCover() instanceof Asset) {
            $object->setCoverUrl($this->generateAssetUrl($object->getCover()));
        }
    }

    public function support($object, $format): bool
    {
        return $object instanceof PublicationConfig;
    }
}
