<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class FileDeleteHandler
{
    public function __construct(
        private FileStorageManager $storageManager,
    ) {
    }

    public function __invoke(FileDelete $message): void
    {
        foreach ($message->getPaths() as $path) {
            $this->storageManager->delete($path);
        }
    }
}
