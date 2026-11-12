<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

/**
 * Decides which stored files a browser may render inline.
 *
 * Files are served from the storage origin: a document able to run scripts
 * there (HTML, SVG, XML…) would be a stored XSS. Only passive media types are
 * displayed inline, everything else is downloaded as an opaque binary.
 */
final class InlineContentTypePolicy
{
    public const string ATTACHMENT_CONTENT_TYPE = 'application/octet-stream';

    private const array INLINE_TYPE_PREFIXES = [
        'image/',
        'video/',
        'audio/',
    ];

    private const array INLINE_TYPES = [
        'application/pdf',
    ];

    private const array NEVER_INLINE_TYPES = [
        'image/svg+xml',
    ];

    /**
     * Response headers a signed URL must force, whatever the Content-Type stored
     * on the object (the one declared by the uploading client).
     *
     * Inline display needs both an explicit request ($inline) and a known type
     * allowed inline. Otherwise the file is an attachment, typed as such only
     * when it could have been displayed.
     *
     * @param string|null $type the MIME type known by the application, not the stored one
     *
     * @return array{contentType: string, contentDisposition: string|null}
     */
    public static function getResponseHeaders(?string $type, string $filename, bool $inline = false): array
    {
        $type = self::normalize($type);
        $displayable = self::canBeDisplayedInline($type);

        if ($inline && $displayable) {
            return [
                'contentType' => $type,
                'contentDisposition' => null,
            ];
        }

        return [
            'contentType' => $displayable ? $type : self::ATTACHMENT_CONTENT_TYPE,
            'contentDisposition' => sprintf('attachment; filename=%s', $filename),
        ];
    }

    public static function canBeDisplayedInline(?string $type): bool
    {
        $type = self::normalize($type);
        if (null === $type) {
            return false;
        }

        if (in_array($type, self::NEVER_INLINE_TYPES, true)) {
            return false;
        }

        if (in_array($type, self::INLINE_TYPES, true)) {
            return true;
        }

        foreach (self::INLINE_TYPE_PREFIXES as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lower-cased essence of a MIME type ("Image/JPEG; foo=bar" → "image/jpeg"),
     * null if it is not a valid "type/subtype".
     */
    public static function normalize(?string $type): ?string
    {
        if (null === $type) {
            return null;
        }

        $type = strtolower(trim(explode(';', $type, 2)[0]));
        if (1 !== preg_match('#^[a-z0-9][a-z0-9!\#$&^_.+-]*/[a-z0-9][a-z0-9!\#$&^_.+-]*$#', $type)) {
            return null;
        }

        return $type;
    }
}
