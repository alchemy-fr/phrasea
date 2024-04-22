<?php

namespace App\Consumer\Handler\File;

final readonly class CopyFileToAsset
{
    public function __construct(
        private string $assetId,
        private string $fileId,
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
