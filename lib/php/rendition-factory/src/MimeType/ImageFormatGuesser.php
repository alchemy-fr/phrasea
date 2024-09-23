<?php

namespace Alchemy\RenditionFactory\MimeType;

final readonly class ImageFormatGuesser
{
    public static function getFormat(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            default => null,
        };
    }
}
