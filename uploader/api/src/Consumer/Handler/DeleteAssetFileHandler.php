<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteAssetFileHandler
{
    public function __construct(private FileStorageManager $storageManager)
    {
    }

    public function __invoke(DeleteAssetFile $message): void
    {
        try {
            $this->storageManager->delete($message->getPath());
        } catch (FileNotFoundException) {
        }
    }
}
