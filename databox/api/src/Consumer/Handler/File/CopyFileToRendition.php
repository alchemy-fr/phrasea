<?php

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class CopyFileToRendition
{
    public function __construct(
        private string $renditionId,
        private string $fileId
    ) {
    }

    public function getRenditionId(): string
    {
        return $this->renditionId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
