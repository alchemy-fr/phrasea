<?php

namespace App\Consumer\Handler\File;

final readonly class ImportFile
{
    public function __construct(
        private string $fileId
    ) {
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
