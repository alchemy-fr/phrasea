<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Admin\ClientUrlGenerator;
use App\Entity\PublicationShare;

final readonly class PublicationShareNormalizer implements EntityNormalizerInterface
{
    public function __construct(private ClientUrlGenerator $clientUrlGenerator)
    {
    }

    /**
     * @param PublicationShare $object
     */
    public function normalize($object, array &$context = []): void
    {
        $object->setUrl($this->clientUrlGenerator->generateShareUrl($object));
    }

    public function support($object): bool
    {
        return $object instanceof PublicationShare;
    }
}
