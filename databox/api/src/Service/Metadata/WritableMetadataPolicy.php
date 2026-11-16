<?php

declare(strict_types=1);

namespace App\Service\Metadata;

/**
 * Decides which metadata tags Databox lets exiftool write into a file.
 *
 * The tags to write are chosen by workspace editors (attribute and rendition definitions), so
 * only descriptive metadata families are allowed. Some exiftool tags act on the file system
 * instead of the file content (File:HardLink, File:SymLink, System:FileName, System:Directory,
 * File:Geotag…): writing them would create links, move files or read arbitrary paths.
 *
 * The check must be made on the canonical tag id returned by phpexiftool
 * (TagGroupInterface::getId()), never on the raw string entered by the user: phpexiftool
 * resolves tag ids case-insensitively.
 */
final readonly class WritableMetadataPolicy
{
    /**
     * Lower-cased tag groups whose tags may be written. Every "XMP-*" group is allowed as well.
     */
    private const array ALLOWED_GROUPS = [
        'exif',
        'iptc',
        'xmp',
        'ifd0',
        'exififd',
        'gps',
        'photoshop',
        'quicktime',
        'id3',
        'pdf',
        'keys',
        'itemlist',
        'userdata',
        // "Canon:ImageUniqueID" is a default target of the doc_unique_id file analyzer
        'canon',
    ];

    /**
     * Lower-cased tag groups that are never written, even if the allowed list grows.
     */
    private const array DENIED_GROUPS = [
        'file',
        'system',
        'composite',
        'exiftool',
        'extra',
    ];

    public function isWritable(string $tagGroupId): bool
    {
        if (!str_contains($tagGroupId, ':')) {
            return false;
        }

        $group = strtolower(explode(':', $tagGroupId, 2)[0]);

        if (in_array($group, self::DENIED_GROUPS, true)) {
            return false;
        }

        return in_array($group, self::ALLOWED_GROUPS, true)
            || str_starts_with($group, 'xmp-');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertWritable(string $tagGroupId): void
    {
        if (!$this->isWritable($tagGroupId)) {
            throw new \InvalidArgumentException(sprintf('Metadata tag "%s" is not allowed to be written', $tagGroupId));
        }
    }
}
