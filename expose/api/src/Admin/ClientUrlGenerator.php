<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Publication;
use App\Entity\PublicationShare;

readonly class ClientUrlGenerator
{
    public function __construct(private string $clientBaseUrl)
    {
    }

    public function generatePublicationUrl(Publication $publication): string
    {
        return sprintf('%s/%s', $this->clientBaseUrl, $publication->getSlug() ?? $publication->getId());
    }

    public function generateShareUrl(PublicationShare $share): string
    {
        return sprintf('%s/s/%s', $this->clientBaseUrl, $share->getToken());
    }
}
