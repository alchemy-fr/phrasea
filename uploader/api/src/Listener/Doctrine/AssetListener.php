<?php

declare(strict_types=1);

namespace App\Listener\Doctrine;

use App\Consumer\Handler\DeleteAssetFileHandler;
use App\Entity\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class AssetListener implements EventSubscriber
{
    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        $asset = $event->getEntity();
        if (!$asset instanceof Asset) {
            return;
        }

        $this->eventProducer->publish(new EventMessage(DeleteAssetFileHandler::EVENT, [
            'path' => $asset->getPath(),
        ]));
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postRemove,
        ];
    }
}
