<?php

declare(strict_types=1);

namespace App\Twig;

use App\Admin\ClientUrlGenerator;
use App\Entity\Publication;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    private ClientUrlGenerator $clientUrlGenerator;

    public function __construct(ClientUrlGenerator $clientUrlGenerator)
    {
        $this->clientUrlGenerator = $clientUrlGenerator;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('generate_publication_url', [$this, 'generatePublicationUrl']),
        ];
    }

    public function generatePublicationUrl(Publication $publication): string
    {
        return $this->clientUrlGenerator->generatePublicationUrl($publication);
    }
}
