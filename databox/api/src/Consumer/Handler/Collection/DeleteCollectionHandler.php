<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Doctrine\Delete\CollectionDelete;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteCollectionHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'delete_collection';
    private CollectionDelete $collectionDelete;

    public function __construct(CollectionDelete $collectionDelete)
    {
        $this->collectionDelete = $collectionDelete;
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
