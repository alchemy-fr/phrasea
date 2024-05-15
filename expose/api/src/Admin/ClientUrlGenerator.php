<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Publication;

readonly class ClientUrlGenerator
{
    public function __construct(private string $clientBaseUrl)
    {
    }

    public function generatePublicationUrl(Publication $publication): string
    {
        return sprintf('%s/%s', $this->clientBaseUrl, $publication->getSlug() ?? $publication->getId());
    }
}
