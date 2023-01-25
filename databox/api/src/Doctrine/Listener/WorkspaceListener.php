<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Workspace\OnWorkspaceDeleteHandler;
use App\Entity\Core\Workspace;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class WorkspaceListener implements EventSubscriber
{
    private PostFlushStack $postFlushStack;

    public function __construct(PostFlushStack $postFlushStack)
    {
        $this->postFlushStack = $postFlushStack;
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof Workspace) {
            $this->postFlushStack->addEvent(OnWorkspaceDeleteHandler::createEvent($object->getId()));
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
        ];
    }
}
