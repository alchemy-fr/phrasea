<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Util;

use Symfony\Component\Mime\MimeTypes;

final class FileUtil
{
    public static function isImageType(?string $mimeType): bool
    {
        return 1 === preg_match('#^image/#', $mimeType ?? '');
    }

    public static function isVideoType(?string $mimeType): bool
    {
        return 1 === preg_match('#^video/#', $mimeType ?? '');
    }

    public static function guessExtension(?string $type, ?string $path): ?string
    {
        $ext = self::getExtensionFromType($type);
        if (null === $ext) {
            $ext = self::getExtensionFromPath($path) ?: null;
        }

        return $ext;
    }

    public static function getExtensionFromPath(string $path): string
    {
        $path = preg_replace('#\?.*$#', '', $path);

        return pathinfo($path, PATHINFO_EXTENSION) ?? '';
    }

    public static function getExtensionFromType(?string $type): ?string
    {
        if (null === $type) {
            return null;
        }

        $mimeTypes = new MimeTypes();

        $extensions = $mimeTypes->getExtensions($type);

        if (empty($extensions)) {
            return null;
        }

        return $extensions[0];
    }

    public static function getTypeFromExtension(?string $extension): ?string
    {
        if (null === $extension) {
            return null;
        }

        $mimeTypes = new MimeTypes();

        $types = $mimeTypes->getMimeTypes($extension);

        if (empty($types)) {
            return null;
        }

        return $types[0];
    }
}
