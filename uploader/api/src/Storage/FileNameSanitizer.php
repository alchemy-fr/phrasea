<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * The original name of an asset, as the storage and the consumers can take it: without directory nor control
 * character, and short enough for its column.
 */
final class FileNameSanitizer
{
    /**
     * In bytes: the column of the original name of the assets is 255 characters long.
     */
    final public const int MAX_LENGTH = 255;

    /**
     * Longer, what follows the last dot is not taken for an extension to keep when truncating.
     */
    private const int MAX_EXTENSION_LENGTH = 20;

    /**
     * @param string $fallback when nothing is left of the name
     */
    public static function sanitize(string $name, string $fallback = 'file'): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }

        // The last segment, "\" counting as a separator
        $name = preg_replace('#^.*[/\\\\]#su', '', $name);
        $name = trim(preg_replace('#\p{Cc}+#u', '', $name));
        if ('' === trim($name, '.')) {
            return $fallback;
        }

        if (strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if ('' !== $extension && strlen($extension) <= self::MAX_EXTENSION_LENGTH) {
            $suffix = '.'.$extension;
            $base = substr($name, 0, -strlen($suffix));

            return rtrim(mb_strcut($base, 0, self::MAX_LENGTH - strlen($suffix), 'UTF-8')).$suffix;
        }

        return mb_strcut($name, 0, self::MAX_LENGTH, 'UTF-8');
    }
}
