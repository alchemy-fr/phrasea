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
    public function __construct(private readonly ProxyCachePurger $proxyCachePurger)
    {
    }

    private function handle(PostUpdateEventArgs|PostPersistEventArgs|PreRemoveEventArgs|LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof EnvVar) {
            $this->proxyCachePurger->purgeRoute('global_config');
        } elseif ($entity instanceof Publication) {
            $this->invalidatePublicationAndAssetsCache($entity);
        } elseif ($entity instanceof Asset) {
            $this->invalidateAssetCache($entity);
            $this->invalidatePublicationCache($entity->getPublication());
        } elseif ($entity instanceof PublicationProfile) {
            foreach ($entity->getPublications() as $publication) {
                $this->invalidatePublicationAndAssetsCache($publication);
            }
        }
    }

    private function invalidatePublicationAndAssetsCache(Publication $publication): void
    {
        $this->invalidatePublicationCache($publication);

        foreach ($publication->getAssets() as $asset) {
            $this->invalidateAssetCache($asset);
        }
    }

    private function invalidatePublicationCache(Publication $publication): void
    {
        $this->proxyCachePurger->purgeRoute(Publication::GET_PUBLICATION_ROUTE_NAME, [
            'id' => $publication->getId(),
        ]);
        if ($publication->getSlug()) {
            $this->proxyCachePurger->purgeRoute(Publication::GET_PUBLICATION_ROUTE_NAME, [
                'id' => $publication->getSlug(),
            ]);
        }
    }

    private function invalidateAssetCache(Asset $asset): void
    {
        $this->proxyCachePurger->purgeRoute(Asset::GET_ASSET_ROUTE_NAME, [
            'id' => $asset->getId(),
        ]);
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
