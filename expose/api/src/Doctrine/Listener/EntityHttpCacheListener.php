<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Asset;
use App\Entity\EnvVar;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
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

    private function handle(PostUpdateEventArgs|PostPersistEventArgs|PreRemoveEventArgs|LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof EnvVar) {
            $this->proxyCachePurger->purgeRoute('global_config');
        } elseif ($entity instanceof Publication) {
            $this->invalidatePublicationCache($entity);
        } elseif ($entity instanceof Asset) {
            $this->invalidatePublicationCache($entity->getPublication());
        } elseif ($entity instanceof PublicationProfile) {
            foreach ($entity->getPublications() as $publication) {
                $this->invalidatePublicationCache($publication);
            }
        }
    }

    private function invalidatePublicationCache(Publication $publication): void
    {
        $this->proxyCachePurger->purgeRoute('api_publications_get_item', [
            'id' => $publication->getId(),
        ]);
        if ($publication->getSlug()) {
            $this->proxyCachePurger->purgeRoute('api_publications_get_item', [
                'id' => $publication->getSlug(),
            ]);
        }
    }

    public function postUpdate(PostUpdateEventArgs|LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function postPersist(PostPersistEventArgs|LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function preRemove(PreRemoveEventArgs|LifecycleEventArgs $args): void
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
