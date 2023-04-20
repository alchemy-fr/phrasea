<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\EnvVar;
use App\Entity\Publication;
use App\Http\Cache\ProxyCachePurger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

class EntityHttpCacheListener implements EventSubscriber
{
    private ProxyCachePurger $proxyCachePurger;

    public function __construct(ProxyCachePurger $proxyCachePurger)
    {
        $this->proxyCachePurger = $proxyCachePurger;
    }

    private function handle(PostUpdateEventArgs|PostPersistEventArgs|PreRemoveEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof EnvVar) {
            $this->proxyCachePurger->purgeRoute('global_config');
        } elseif ($entity instanceof Publication) {
            $this->proxyCachePurger->purgeRoute('api_publications_get_item', [
                'id' => $entity->getId(),
            ]);
            if ($entity->getSlug()) {
                $this->proxyCachePurger->purgeRoute('api_publications_get_item', [
                    'id' => $entity->getSlug(),
                ]);
            }
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->handle($args);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->handle($args);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->handle($args);
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
        ];
    }
}
