<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use League\Flysystem\FileNotFoundException;

class DeleteAssetFileHandler extends AbstractEntityManagerHandler
{
    public const EVENT = 'delete_asset_file';

    private FileStorageManager $storageManager;

    public function __construct(FileStorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function handle(EventMessage $message): void
    {
        $path = $message->getPayload()['path'];

        try {
            $this->storageManager->delete($path);
        } catch (FileNotFoundException $e) {
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'fast_events';
    }
}
