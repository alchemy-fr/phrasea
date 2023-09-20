<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\StorageBundle\Storage\PathGenerator;

final readonly class FilePathGenerator
{
    public function __construct(private PathGenerator $pathGenerator)
    {
    }

    public function generatePath(string $workspaceId, ?string $extension): string
    {
        if (empty($extension)) {
            throw new \InvalidArgumentException('Files must have an extension');
        }

        return $this->pathGenerator->generatePath(
            $extension,
            sprintf('files/%s/%s/', $workspaceId, date('Y/m/d'))
        );
    }
}
