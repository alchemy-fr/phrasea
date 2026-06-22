<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

final readonly class NotifyAssetTopic
{
    public function __construct(
        private string $event,
        private string $assetId,
        private string $authorId,
        private ?string $assetName = null,
    ) {
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getAssetName(): ?string
    {
        return $this->assetName;
    }
}
