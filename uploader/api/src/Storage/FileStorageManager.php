<?php

declare(strict_types=1);

namespace App\Storage;

use Ramsey\Uuid\Uuid;

class FileStorageManager
{
    public function generatePath(?string $extension): string
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

        return $path;
    }
}
