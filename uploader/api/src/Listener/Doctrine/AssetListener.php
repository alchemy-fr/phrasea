<?php

declare(strict_types=1);

namespace App\Listener\Doctrine;

use App\Consumer\Handler\DeleteAssetFileHandler;
use App\Entity\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postRemove)]
readonly class AssetListener implements EventSubscriber
{
    public function __construct(private EventProducer $eventProducer)
    {
    }

    public function postRemove(PostRemoveEventArgs $event)
    {
        $asset = $event->getEntity();
        if (!$asset instanceof Asset) {
            return;
        }

        $this->eventProducer->publish(new EventMessage(DeleteAssetFileHandler::EVENT, [
            'path' => $asset->getPath(),
        ]));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postRemove,
        ];
    }
}
