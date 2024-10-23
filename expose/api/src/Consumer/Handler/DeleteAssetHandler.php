<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteAssetHandler
{
    final public const string EVENT = 'delete_asset';

    public function __construct(private FileStorageManager $storageManager)
    {
    }

    public function __invoke(DeleteAsset $message): void
    {
        $this->storageManager->delete($message->getPath());
    }
}
