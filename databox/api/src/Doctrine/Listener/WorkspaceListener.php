<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Workspace\OnWorkspaceDeleteHandler;
use App\Entity\Core\Workspace;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class WorkspaceListener extends PostFlushStackListener
{
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getEntity();

        if ($object instanceof Workspace) {
            $this->addEvent(OnWorkspaceDeleteHandler::createEvent($object->getId()));
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
        ];
    }
}
