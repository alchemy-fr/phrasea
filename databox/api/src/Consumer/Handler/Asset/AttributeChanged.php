<?php

namespace App\Consumer\Handler\Asset;

final readonly class AttributeChanged
{
    public function __construct(
        private array $attributes,
        private string $assetId,
        private ?string $userId
    ) {
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }
}
