<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Asset;

class AssetNormalizer implements EntityNormalizerInterface
{
    private UrlSigner $urlSigner;

    public function __construct(UrlSigner $urlSigner)
    {
        $this->urlSigner = $urlSigner;
    }

    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        $object->setUrl($this->urlSigner->getSignedUrl($object->getPath()));
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
