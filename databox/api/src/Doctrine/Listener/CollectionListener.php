<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class CollectionListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

    private PostFlushStack $postFlushStack;

    public function __construct(PostFlushStack $postFlushStack)
    {
        $this->postFlushStack = $postFlushStack;
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Collection) {
            return;
        }

        if (!$this->hasChangedField([
            'public',
            'owner',
            'parent',
        ], $args->getEntityManager(), $entity)) {
            return;
        }

        $this->postFlushStack->addEvent(new EventMessage(IndexCollectionBranchHandler::EVENT, [
            'id' => $entity->getId(),
        ]));
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postUpdate => 'postUpdate',
        ];
    }
}
