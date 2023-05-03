<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use App\Entity\Core\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class AssetDeleteHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'asset_delete';

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, self::class);
        }

        $em->remove($asset);
        $em->flush();
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
