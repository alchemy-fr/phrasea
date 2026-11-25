<?php

namespace Alchemy\RenditionFactory\MimeType;

use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Guesses types from extensions with the MIME types of the application, which
 * may know the usual type of an extension listed after another one by Symfony
 * (storage-bundle puts "video/x-ms-wmv" first for ".wmv").
 */
final readonly class MimeTypeGuesser
{
    public function __construct(private MimeTypesInterface $mimeTypes = new MimeTypes())
    {
    }

    public function guessMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->mimeTypes->getMimeTypes($extension)[0] ?? null;
    }

    public function getExtension(string $mimeType): ?string
    {
        return $this->mimeTypes->getExtensions($mimeType)[0] ?? null;
    }
}
