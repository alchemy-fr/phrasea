<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\Mime\MimeTypes;

class ExtensionUtil
{
    public static function guessExtension(?string $type, ?string $path): ?string
    {
        $ext = static::getExtensionFromType($type);
        if (null === $ext) {
            $ext = static::getExtensionFromPath($path) ?: null;
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
