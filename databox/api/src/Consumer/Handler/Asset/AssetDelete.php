<?php

namespace App\Consumer\Handler\Asset;

final readonly class AssetDelete
{
    public function __construct(
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
