<?php

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class ImportFile
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
