<?php

namespace App\Consumer\Handler\Phraseanet;

final readonly class PhraseanetDownloadSubdef
{
    public function __construct(
        private string $assetId,
        private string $databoxId,
        private string $recordId,
        private string $subdefName,
        private string $permalink,
        private ?string $type = null,
        private ?int $size = null,
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getDataboxId(): string
    {
        return $this->databoxId;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }

    public function getPermalink(): string
    {
        return $this->permalink;
    }

    public function getSubdefName(): string
    {
        return $this->subdefName;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }
}
