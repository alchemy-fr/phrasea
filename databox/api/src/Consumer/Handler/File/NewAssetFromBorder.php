<?php

namespace App\Consumer\Handler\File;

final readonly class NewAssetFromBorder
{
    public function __construct(
        private string $userId,
        private string $fileId,
        private array $collectionIds,
        private ?string $title = null,
        private ?string $filename = null,
        private ?array $formData = null,
        private ?string $locale = null,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getCollectionIds(): array
    {
        return $this->collectionIds;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getFormData(): ?array
    {
        return $this->formData;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
