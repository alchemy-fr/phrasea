<?php

namespace App\Consumer\Handler\Asset;

readonly class AssetMove
{
    public function __construct(
        private string $id,
        private string $destination,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }
}
