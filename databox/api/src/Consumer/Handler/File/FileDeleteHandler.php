<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class FileDeleteHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'file_delete';

    private FileStorageManager $storageManager;

    public function __construct(FileStorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $paths = $payload['paths'];

        foreach ($paths as $path) {
            $this->storageManager->delete($path);
        }
    }

    public static function createEvent(array $paths): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'paths' => $paths,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
