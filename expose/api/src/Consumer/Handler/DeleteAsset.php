<?php

namespace App\Consumer\Handler;

final readonly class DeleteAsset
{
    public function __construct(private string $path)
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
