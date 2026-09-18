<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\UnableToDeleteFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteFileFromStorageHandler
{
    public function __construct(
        private FileStorageManager $storageManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteFileFromStorage $message): void
    {
        foreach ($message->getPaths() as $path) {
            $path = trim($path);
            if ('' === $path) {
                continue;
            }

            try {
                $this->storageManager->delete($path);
            } catch (CorruptedPathDetected|UnableToDeleteFile $e) {
                // The row is already gone: retrying with the same path can only fail again.
                $this->logger->warning('Could not delete file from storage', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
