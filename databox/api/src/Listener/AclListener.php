<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Api\OutputTransformer\CollectionOutputTransformer;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\Handler\Search\IndexAllCollections;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsEventListener(event: AclUpsertEvent::NAME, method: 'onAclUpsert')]
#[AsEventListener(event: AclDeleteEvent::NAME, method: 'onAclDelete')]
readonly class AclListener
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        private ObjectMapping $objectMapping,
        private MessageBusInterface $bus,
        private TagAwareCacheInterface $collectionCache,
    ) {
    }

    public function onAclUpsert(AclUpsertEvent $event): void
    {
        $this->indexObject($event->getObjectType(), $event->getObjectId());
    }

    public function onAclDelete(AclDeleteEvent $event): void
    {
        $this->indexObject($event->getObjectType(), $event->getObjectId());
    }

    private function indexObject(string $objectType, ?string $objectId = null): void
    {
        $this->collectionCache->invalidateTags([CollectionOutputTransformer::COLLECTION_CACHE_NS]);

        $objectClass = $this->objectMapping->getClassName($objectType);

        if (null === $objectId) {
            switch ($objectClass) {
                case Asset::class:
                    $this->bus->dispatch(new IndexAllAssets());
                    break;
                case Collection::class:
                    $this->bus->dispatch(new IndexAllCollections());
                    break;
            }

            return;
        }

        if (Collection::class === $objectClass) {
            $this->bus->dispatch(new IndexCollectionBranch($objectId));
        } else {
            $this->searchIndexer->scheduleObjectsIndex($objectClass, [$objectId], Operation::Upsert);
        }
    }
}
