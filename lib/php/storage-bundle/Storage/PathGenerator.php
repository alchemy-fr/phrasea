<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

use Ramsey\Uuid\Uuid;

final readonly class PathGenerator implements PathGeneratorInterface
{
    public function generatePath(?string $extension, string $prefix = ''): string
    {
        $uuid = Uuid::uuid4()->toString();

        $path = implode(DIRECTORY_SEPARATOR, [
            substr($uuid, 0, 2),
            substr($uuid, 2, 2),
            $uuid,
        ]);

        // Defensive: an extension must never carry path separators, whitespace or control chars.
        $extension = preg_replace('/[^A-Za-z0-9]/', '', (string) $extension);
        if ('' !== $extension) {
            $path .= '.'.$extension;
        }

        return $prefix.$path;
    }
}
