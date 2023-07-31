<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Workspace\OnWorkspaceDeleteHandler;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::preRemove)]
class WorkspaceListener implements EventSubscriber
{
    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof Workspace) {
            $this->postFlushStack->addEvent(OnWorkspaceDeleteHandler::createEvent($object->getId()));
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
        ];
    }
}
