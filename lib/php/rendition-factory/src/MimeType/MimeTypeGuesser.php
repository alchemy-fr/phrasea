<?php

namespace Alchemy\RenditionFactory\MimeType;

use Symfony\Component\Mime\MimeTypes;

final readonly class MimeTypeGuesser
{
    /**
     * The usual type of an extension when Symfony MIME lists another one first
     * (it gives `audio/x-ms-wmv` before `video/x-ms-wmv` for `wmv`).
     */
    private const array PREFERRED_TYPES = [
        'wmv' => 'video/x-ms-wmv',
    ];

    public function __construct(private MimeTypes $mimeTypes = new MimeTypes())
    {
    }

    public function guessMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = $this->mimeTypes->getMimeTypes($extension);

        $preferred = self::PREFERRED_TYPES[$extension] ?? null;
        if (null !== $preferred && in_array($preferred, $types, true)) {
            return $preferred;
        }

        return $types[0] ?? null;
    }

    public function getExtension(string $mimeType): ?string
    {
        return $this->mimeTypes->getExtensions($mimeType)[0] ?? null;
    }
}
