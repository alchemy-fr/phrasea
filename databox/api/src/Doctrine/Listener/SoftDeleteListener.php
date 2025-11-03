<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Workspace\DeleteWorkspace;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

#[AsDoctrineListener(SoftDeleteableListener::PRE_SOFT_DELETE)]
readonly class SoftDeleteListener
{
    public function __construct(private PostFlushStack $postFlushStack)
    {
    }

    public function preSoftDelete(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof SoftDeleteableInterface) {
            if (null !== $entity->getDeletedAt()) {
                // Already being deleted
                return;
            }

            if ($entity instanceof Workspace) {
                $this->postFlushStack->addBusMessage(new DeleteWorkspace($entity->getId()));
            }
        }
    }
}
