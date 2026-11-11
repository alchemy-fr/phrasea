<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Doctrine\Listener\EntityHttpCacheListener;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Http\Cache\ProxyCachePurger;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

class EntityHttpCacheListenerTest extends AbstractExposeTestCase
{
    public function testPublicationUpdatePurgesParentAndDescendants(): void
    {
        $root = $this->createPublication(['slug' => 'root']);
        $child = $this->createPublication(['parent' => $root]);
        $grandChild = $this->createPublication(['parent' => $child]);
        $grandChildAssetId = $this->createAsset($grandChild);
        $sibling = $this->createPublication(['parent' => $root]);
        self::getEntityManager()->refresh($grandChild);

        $purger = $this->createPurgerSpy();
        $listener = new EntityHttpCacheListener($purger);

        $listener->postUpdate(new PostUpdateEventArgs($child, self::getEntityManager()));

        $this->assertEqualsCanonicalizing([
            Publication::GET_PUBLICATION_ROUTE_NAME.':'.$root->getId(),
            Publication::GET_PUBLICATION_ROUTE_NAME.':root',
            Publication::GET_PUBLICATION_ROUTE_NAME.':'.$child->getId(),
            Publication::GET_PUBLICATION_ROUTE_NAME.':'.$grandChild->getId(),
            Asset::GET_ASSET_ROUTE_NAME.':'.$grandChildAssetId,
        ], $purger->purged);
        $this->assertNotContains(Publication::GET_PUBLICATION_ROUTE_NAME.':'.$sibling->getId(), $purger->purged);
    }

    public function testMovingPublicationPurgesOldAndNewParents(): void
    {
        $oldParent = $this->createPublication(['slug' => 'old-parent']);
        $newParent = $this->createPublication();
        $child = $this->createPublication(['parent' => $oldParent]);

        $em = self::getEntityManager();
        $purger = $this->createPurgerSpy();
        $listener = new EntityHttpCacheListener($purger);
        $em->getEventManager()->addEventListener(Events::postUpdate, $listener);
        try {
            $child->setParent($newParent);
            $em->flush();
        } finally {
            $em->getEventManager()->removeEventListener(Events::postUpdate, $listener);
        }

        $this->assertContains(Publication::GET_PUBLICATION_ROUTE_NAME.':'.$oldParent->getId(), $purger->purged);
        $this->assertContains(Publication::GET_PUBLICATION_ROUTE_NAME.':old-parent', $purger->purged);
        $this->assertContains(Publication::GET_PUBLICATION_ROUTE_NAME.':'.$newParent->getId(), $purger->purged);
        $this->assertContains(Publication::GET_PUBLICATION_ROUTE_NAME.':'.$child->getId(), $purger->purged);
    }

    public function testWebVTTArePurgedWithTheirPublication(): void
    {
        $parent = $this->createPublication();
        $child = $this->createPublication(['parent' => $parent]);
        $assetId = $this->createAsset($child);
        $em = self::getEntityManager();
        $asset = $em->find(Asset::class, $assetId);
        $asset->setWebVTT([['label' => 'English', 'locale' => 'en', 'content' => 'WEBVTT']]);
        $em->flush();
        $vttId = $asset->getWebVTT()[0]['id'];
        $em->refresh($child);

        $purger = $this->createPurgerSpy();
        $listener = new EntityHttpCacheListener($purger);
        $listener->postUpdate(new PostUpdateEventArgs($parent, $em));

        $this->assertContains(Publication::GET_PUBLICATION_ROUTE_NAME.':'.$child->getId(), $purger->purged);
        $this->assertContains(Asset::GET_ASSET_ROUTE_NAME.':'.$assetId, $purger->purged);
        $this->assertContains(Asset::GET_WEBVTT_ROUTE_NAME.':'.$assetId.':'.$vttId, $purger->purged);
    }

    public function testRemovedWebVTTArePurged(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
        $em = self::getEntityManager();
        $asset = $em->find(Asset::class, $assetId);
        $asset->setWebVTT([['label' => 'English', 'locale' => 'en', 'content' => 'WEBVTT']]);
        $em->flush();
        $removedId = $asset->getWebVTT()[0]['id'];

        $purger = $this->createPurgerSpy();
        $listener = new EntityHttpCacheListener($purger);
        $em->getEventManager()->addEventListener(Events::postUpdate, $listener);
        try {
            $asset->setWebVTT([['label' => 'French', 'locale' => 'fr', 'content' => 'WEBVTT']]);
            $em->flush();
        } finally {
            $em->getEventManager()->removeEventListener(Events::postUpdate, $listener);
        }

        $this->assertContains(Asset::GET_WEBVTT_ROUTE_NAME.':'.$assetId.':'.$removedId, $purger->purged);
        $this->assertContains(Asset::GET_WEBVTT_ROUTE_NAME.':'.$assetId.':'.$asset->getWebVTT()[0]['id'], $purger->purged);
    }

    private function createPurgerSpy(): ProxyCachePurger
    {
        return new class extends ProxyCachePurger {
            public array $purged = [];

            public function __construct()
            {
            }

            public function purgeRoute(string $routeName, array $parameter = []): void
            {
                $this->purged[] = implode(':', [$routeName, ...array_values($parameter)]);
            }
        };
    }
}
