<?php

namespace App\Security;

use App\Entity\Core\Asset;

final readonly class ClientUrlHelper
{
    private const string UNKNOWN_RENDITION = '_';

    public function __construct(
        private string $databoxClientBaseUrl,
    ) {
    }

    public function generateAssetUrl(Asset $asset): string
    {
        return sprintf(
            '%s/assets?_m=%s',
            $this->databoxClientBaseUrl,
            urlencode(sprintf('/assets/%s/%s', $asset->getId(), self::UNKNOWN_RENDITION))
        );
    }
}
