<?php

namespace App\Consumer\Handler;

final readonly class ZippyDownloadRequest
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
