<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class CollectionListener extends PostFlushStackListener
{
    use ChangeFieldListenerTrait;

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Collection) {
            return;
        }

        if (!$this->hasChangedField([
            'public',
            'owner',
        ], $args->getEntityManager(), $entity)) {
            return;
        }

        $this->addEvent(new EventMessage(IndexCollectionBranchHandler::EVENT, [
            'id' => $entity->getId(),
        ]));
    }

    public function getSubscribedEvents()
    {
        return array_merge(parent::getSubscribedEvents(), [
            Events::postUpdate => 'postUpdate',
        ]);
    }
}
