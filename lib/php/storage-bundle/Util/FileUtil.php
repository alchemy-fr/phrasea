<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Util;

use Symfony\Component\Mime\MimeTypes;

final class FileUtil
{
    private const array COMPOUND_EXTENSIONS = [
        'tar.gz',
        'tar.bz2',
        'tar.xz',
        'tar.zst',
        'tar.lz',
        'tar.lzma',
        'tar.br',
    ];

    public static function isImageType(?string $mimeType): bool
    {
        return 1 === preg_match('#^image/#', $mimeType ?? '');
    }

    public static function isVideoType(?string $mimeType): bool
    {
        return 1 === preg_match('#^video/#', $mimeType ?? '');
    }

    public static function isAudioType(?string $mimeType): bool
    {
        return 1 === preg_match('#^audio/#', $mimeType ?? '');
    }

    public static function guessExtension(?string $type, ?string $path): ?string
    {
        $ext = self::getExtensionFromType($type);

        if (null === $ext) {
            if (null !== $path) {
                return self::getExtensionFromPath($path);
            }

            return null;
        }

        return $ext;
    }

    public static function getExtensionFromPath(string $path): ?string
    {
        $path = preg_replace('#\?.*$#', '', $path);

        $basename = pathinfo($path, PATHINFO_BASENAME);
        foreach (self::COMPOUND_EXTENSIONS as $compoundExtension) {
            if (preg_match('#[^.]\.'.preg_quote($compoundExtension, '#').'$#i', $basename)) {
                return substr($basename, -strlen($compoundExtension));
            }
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?? '';
        if (!preg_match('#^[a-z0-9]{2,5}$#i', $extension)) {
            return null;
        }

        return self::normalizeExtension($extension);
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

        return self::normalizeExtension($extensions[0]);
    }

    public static function getTypeFromExtension(?string $extension): ?string
    {
        if (null === $extension) {
            return null;
        }

        $mimeTypes = new MimeTypes();

        $types = $mimeTypes->getMimeTypes($extension);

        if (empty($types)) {
            if (false !== ($pos = strrpos($extension, '.'))) {
                return self::getTypeFromExtension(substr($extension, $pos + 1));
            }

            return null;
        }

        return $types[0];
    }

    public static function stripExtension(string $filename): string
    {
        $extension = self::normalizeExtension(self::getExtensionFromPath($filename));
        if (null === $extension || !str_ends_with(strtolower($filename), '.'.strtolower($extension))) {
            return $filename;
        }

        return substr($filename, 0, -(strlen($extension) + 1));
    }

    public static function normalizeExtension(?string $extension): ?string
    {
        if (null === $extension) {
            return null;
        }

        $extension = trim(strtolower($extension));

        return $extension ?: null;
    }
}
