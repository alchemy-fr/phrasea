<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\StorageBundle\Storage\PathGenerator;

class FilePathGenerator
{
    private PathGenerator $pathGenerator;

    public function __construct(PathGenerator $pathGenerator)
    {
        $this->pathGenerator = $pathGenerator;
    }

    public function generatePath(string $workspaceId, ?string $extension): string
    {
        return $this->pathGenerator->generatePath(
            $extension,
            sprintf('files/%s/%s/', $workspaceId, date('Y/m/d'))
        );
    }
}
