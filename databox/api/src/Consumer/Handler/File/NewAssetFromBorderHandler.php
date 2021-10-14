<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NewAssetFromBorderHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_asset_from_border';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['fileId'];

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        $asset = new Asset();
        $asset->setFile($file);
        $asset->setOwnerId($payload['userId']);
        $asset->setTitle($payload['title'] ?? $payload['name']);

        $em = $this->getEntityManager();
        $em->persist($asset);
        $em->flush();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
