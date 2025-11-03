<?php

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class AnalyzeFile
{
    public function __construct(
        private string $fileId,
    ) {
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
