<?php

declare(strict_types=1);

namespace App\Entity\Core;

/**
 * Coarse family of a file, derived from its MIME type.
 *
 * Indexed as the `@family` built-in attribute so that assets can be searched
 * and faceted by kind of media, whatever the exact MIME type.
 */
enum FileFamilyEnum: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Other = 'other';

    private const array DOCUMENT_TYPES = [
        'application/pdf',
        'application/rtf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/epub+zip',
    ];

    private const array VIDEO_TYPES = [
        'application/mxf',
        'application/ogg',
    ];

    private const array IMAGE_TYPES = [
        'application/postscript',
        'application/x-photoshop',
        'application/photoshop',
        'application/psd',
        'application/vnd.3gpp.pic-bw-small',
        'application/illustrator',
    ];

    public static function fromMimeType(?string $mimeType): self
    {
        if (null === $mimeType || '' === $mimeType) {
            return self::Other;
        }

        $mimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));

        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return self::Audio;
        }
        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }
        if (str_starts_with($mimeType, 'text/')) {
            return self::Document;
        }

        return match (true) {
            in_array($mimeType, self::DOCUMENT_TYPES, true) => self::Document,
            in_array($mimeType, self::VIDEO_TYPES, true) => self::Video,
            in_array($mimeType, self::IMAGE_TYPES, true) => self::Image,
            default => self::Other,
        };
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $family): string => $family->value, self::cases());
    }
}
