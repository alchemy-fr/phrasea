<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteAssetHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'delete_asset';

    private FileStorageManager $storageManager;

    public function __construct(FileStorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function handle(EventMessage $message): void
    {
        $path = $message->getPayload()['path'];
        $this->storageManager->delete($path);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
