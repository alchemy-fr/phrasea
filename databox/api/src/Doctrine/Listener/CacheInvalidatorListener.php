<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Entity\AbstractUuidEntity;
use App\Repository\Cache\CacheRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(Events::preRemove)]
#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::preUpdate)]
readonly class CacheInvalidatorListener implements EventSubscriber
{
    public function __construct(private PostFlushStack $postFlushStack)
    {
    }

    private function invalidateEntity(LifecycleEventArgs $args): void
    {
        $em = $args->getObjectManager();

        $entity = $args->getObject();

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

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidateEntity($args);
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->invalidateEntity($args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
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
