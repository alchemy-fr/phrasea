<?php

namespace App\Security;

use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ClientUrlHelper
{
    private const string UNKNOWN_RENDITION = '_';

    public function __construct(
        #[Autowire(env: 'DATABOX_CLIENT_URL')]
        private string $clientBaseUrl,
    ) {
    }

    public function generateAssetUrl(Asset $asset): string
    {
        return sprintf(
            '%s/assets?_m=%s',
            $this->clientBaseUrl,
            urlencode(sprintf('/assets/%s/%s', $asset->getId(), self::UNKNOWN_RENDITION))
        );
    }
}
