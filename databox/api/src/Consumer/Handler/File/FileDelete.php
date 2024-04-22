<?php

namespace App\Consumer\Handler\File;

final readonly class FileDelete
{
    public function __construct(
        private array $paths
    ) {
    }

    public function getPaths(): array
    {
        return $this->paths;
    }
}
