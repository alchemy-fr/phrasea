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
    private bool $enabled = true;

    public function postSoftDelete(LifecycleEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getEntity();
        if ($entity instanceof Collection) {
            $this->addEvent(DeleteCollectionHandler::createEvent($entity->getId()));
            return;
        }
        if ($entity instanceof Workspace) {
            $this->addEvent(DeleteWorkspaceHandler::createEvent($entity->getId()));
            return;
        }
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getSubscribedEvents()
    {
        return array_merge(parent::getSubscribedEvents(), [
            SoftDeleteableListener::POST_SOFT_DELETE => 'postSoftDelete',
        ]);
    }
}
