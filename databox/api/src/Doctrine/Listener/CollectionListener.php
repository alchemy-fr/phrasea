<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postUpdate)]
class CollectionListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
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

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
        ];
    }
}
