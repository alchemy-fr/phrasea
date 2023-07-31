<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\AbstractUuidEntity;
use App\Repository\Cache\CacheRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::preRemove)]
#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::preUpdate)]
class CacheInvalidatorListener implements EventSubscriber
{
    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    private function invalidateEntity(LifecycleEventArgs $args): void
    {
        $em = $args->getEntityManager();

        $entity = $args->getEntity();

        if ($entity instanceof AbstractUuidEntity) {
            $repo = $em->getRepository($entity::class);
            if ($repo instanceof CacheRepositoryInterface) {
                $id = $entity->getId();
                $this->postFlushStack->addCallback(function () use ($repo, $id): void {
                    $repo->invalidateEntity($id);
                    $repo->invalidateList();
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

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
