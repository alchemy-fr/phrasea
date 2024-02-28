<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class DeleteAssetFileHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'delete_asset_file';

    public function __construct(private readonly FileStorageManager $storageManager)
    {
    }

    public function handle(EventMessage $message): void
    {
        $path = $message->getPayload()['path'];

        try {
            $this->storageManager->delete($path);
        } catch (FileNotFoundException) {
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
