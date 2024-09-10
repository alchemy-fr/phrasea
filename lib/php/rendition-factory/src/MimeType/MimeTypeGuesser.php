<?php

namespace Alchemy\RenditionFactory\MimeType;

use Symfony\Component\Mime\MimeTypes;

final readonly class MimeTypeGuesser
{
    public function __construct(private MimeTypes $mimeTypes = new MimeTypes())
    {
    }

    public function guessMimeTypeFromPath(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $this->mimeTypes->getMimeTypes($extension)[0] ?? null;
    }

    public function getExtension(string $mimeType): ?string
    {
        return $this->mimeTypes->getExtensions($mimeType)[0] ?? null;
    }

    public function getFormat(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => null,
        };
    }
}
