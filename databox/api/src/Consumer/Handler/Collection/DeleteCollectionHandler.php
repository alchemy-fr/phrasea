<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Doctrine\Delete\CollectionDelete;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteCollectionHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'delete_collection';

    public function __construct(private readonly CollectionDelete $collectionDelete)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->collectionDelete->deleteCollection($payload['id']);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $id): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $id,
        ]);
    }
}
