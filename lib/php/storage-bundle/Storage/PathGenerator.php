<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

use Ramsey\Uuid\Uuid;

final readonly class PathGenerator
{
    public function generatePath(?string $extension, string $prefix = ''): string
    {
        $uuid = Uuid::uuid4()->toString();

        $path = implode(DIRECTORY_SEPARATOR, [
            substr($uuid, 0, 2),
            substr($uuid, 2, 2),
            $uuid,
        ]);

        if ($extension) {
            $path .= '.'.$extension;
        }

        return $prefix.$path;
    }
}
