<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Collection\NotifyCollectionTopic;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postPersist)]
#[AsDoctrineListener(Events::preRemove)]
class CollectionAssetListener implements EventSubscriber
{
    use SecurityAwareTrait;
    use ChangeFieldListenerTrait;

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof CollectionAsset) {
            return;
        }

        $this->trigger(Collection::EVENT_ASSET_ADD, $entity);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof CollectionAsset) {
            return;
        }

        $this->trigger(Collection::EVENT_ASSET_REMOVE, $entity);
    }

    private function trigger(string $event, CollectionAsset $collectionAsset): void
    {
        $user = $this->getUser();
        if (!$user instanceof JwtUser) {
            return;
        }

        $this->postFlushStack->addBusMessage(new NotifyCollectionTopic(
            $event,
            $collectionAsset->getCollection()->getId(),
            $user->getId(),
            $collectionAsset->getAsset()->getId(),
        ));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::preRemove,
        ];
    }
}
