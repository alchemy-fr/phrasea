<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Doctrine\Listener\EntityHttpCacheListener;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Http\Cache\ProxyCachePurger;
use Doctrine\ORM\Event\PostUpdateEventArgs;

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

    private function createPurgerSpy(): ProxyCachePurger
    {
        return new class extends ProxyCachePurger {
            public array $purged = [];

            public function __construct()
            {
            }

            public function purgeRoute(string $routeName, array $parameter = []): void
            {
                $this->purged[] = $routeName.':'.$parameter['id'];
            }
        };
    }
}
