<?php

namespace Alchemy\RenditionFactory\MimeType;

use Symfony\Component\Mime\MimeTypes;

final readonly class MimeTypeGuesser
{
    public function guessMimeTypeFromPath(string $path): string
    {
        $mimeTypes = new MimeTypes();

        return $mimeTypes->guessMimeType($path);
    }
}
