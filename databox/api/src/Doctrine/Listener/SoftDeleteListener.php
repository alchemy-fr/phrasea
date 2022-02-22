<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Collection\DeleteCollectionHandler;
use App\Consumer\Handler\Workspace\DeleteWorkspaceHandler;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

class SoftDeleteListener extends PostFlushStackListener
{
    public function preSoftDelete(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof SoftDeleteableInterface) {
            if (null !== $entity->getDeletedAt()) {
                // Already being deleted
                return;
            }

            if ($entity instanceof Collection) {
                $this->addEvent(DeleteCollectionHandler::createEvent($entity->getId()));

                return;
            }
            if ($entity instanceof Workspace) {
                $this->addEvent(DeleteWorkspaceHandler::createEvent($entity->getId()));

                return;
            }
        }
    }

    public function getSubscribedEvents()
    {
        return array_merge(parent::getSubscribedEvents(), [
            SoftDeleteableListener::PRE_SOFT_DELETE => 'preSoftDelete',
        ]);
    }
}
