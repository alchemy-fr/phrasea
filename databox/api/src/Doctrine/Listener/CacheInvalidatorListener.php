<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\AbstractUuidEntity;
use App\Repository\Cache\CacheRepositoryInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class CacheInvalidatorListener implements EventSubscriber
{
    private PostFlushStack $postFlushStack;

    public function __construct(PostFlushStack $postFlushStack)
    {
        $this->postFlushStack = $postFlushStack;
    }

    private function invalidateEntity(LifecycleEventArgs $args): void
    {
        $em = $args->getEntityManager();

        $entity = $args->getEntity();

        if ($entity instanceof AbstractUuidEntity) {
            $repo = $em->getRepository(get_class($entity));
            if ($repo instanceof CacheRepositoryInterface) {
                $id = $entity->getId();
                $this->postFlushStack->addCallback(function () use ($repo, $id): void {
                    $repo->invalidateEntity($id);
                });
            }
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateEntity($args);
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->invalidateEntity($args);
    }


    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateEntity($args);
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
