<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Collection\DeleteCollectionHandler;
use App\Consumer\Handler\Workspace\DeleteWorkspaceHandler;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

#[AsDoctrineListener(SoftDeleteableListener::PRE_SOFT_DELETE)]
readonly class SoftDeleteListener implements EventSubscriber
{
    public function __construct(private PostFlushStack $postFlushStack)
    {
    }

    public function preSoftDelete(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof SoftDeleteableInterface) {
            if (null !== $entity->getDeletedAt()) {
                // Already being deleted
                return;
            }

            if ($entity instanceof Collection) {
                $this->postFlushStack->addEvent(DeleteCollectionHandler::createEvent($entity->getId()));

                return;
            }
            if ($entity instanceof Workspace) {
                $this->postFlushStack->addEvent(DeleteWorkspaceHandler::createEvent($entity->getId()));

                return;
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            SoftDeleteableListener::PRE_SOFT_DELETE,
        ];
    }
}
