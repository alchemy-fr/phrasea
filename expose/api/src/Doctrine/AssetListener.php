<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Consumer\Handler\DeleteAssetHandler;
use App\Entity\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class AssetListener implements EventSubscriber
{
    private EventProducer $eventProducer;
    /**
     * @var EventMessage[]
     */
    private array $eventStack = [];

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Asset) {
                $this->eventStack[] = new EventMessage(DeleteAssetHandler::EVENT, [
                    'path' => $entity->getPath(),
                ]);
            }
        }
    }

    public function postFlush()
    {
        while ($message = array_shift($this->eventStack)) {
            $this->eventProducer->publish($message);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }
}
