<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Publication;

class ClientUrlGenerator
{
    public function __construct(private readonly string $clientBaseUrl)
    {
    }

    public function generatePublicationUrl(Publication $publication): string
    {
        return sprintf('%s/%s', $this->clientBaseUrl, $publication->getSlug() ?? $publication->getId());
    }
}
