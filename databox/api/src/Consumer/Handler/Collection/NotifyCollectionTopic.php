<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

final readonly class NotifyCollectionTopic
{
    public function __construct(
        private string $event,
        private string $collectionId,
        private string $authorId,
        private ?string $assetId,
        private ?string $assetName = null,
    ) {
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }

    public function getAssetName(): ?string
    {
        return $this->assetName;
    }
}
