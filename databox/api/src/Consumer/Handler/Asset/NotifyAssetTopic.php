<?php

namespace App\Consumer\Handler\Asset;

final readonly class NotifyAssetTopic
{
    public function __construct(
        private string $event,
        private string $assetId,
        private string $authorId,
        private ?string $assetTitle = null,
    )
    {
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

    public function getAssetTitle(): ?string
    {
        return $this->assetTitle;
    }
}
