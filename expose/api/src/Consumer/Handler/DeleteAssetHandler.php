<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteAssetHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'delete_asset';

    public function __construct(private readonly FileStorageManager $storageManager)
    {
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
