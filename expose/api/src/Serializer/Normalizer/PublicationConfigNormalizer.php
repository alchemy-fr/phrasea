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
    public function normalize($object, array &$context = []): void
    {
    }

    public function support($object): bool
    {
        return $object instanceof PublicationConfig;
    }
}
