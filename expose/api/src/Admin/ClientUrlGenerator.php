<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Publication;

class ClientUrlGenerator
{
    private string $clientBaseUrl;

    public function __construct(string $clientBaseUrl)
    {
        $this->clientBaseUrl = $clientBaseUrl;
    }

    public function generatePublicationUrl(Publication $publication): string
    {
        return sprintf('%s/%s', $this->clientBaseUrl, $publication->getSlug() ?? $publication->getId());
    }
}
