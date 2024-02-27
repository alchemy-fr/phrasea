<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Api\OutputTransformer\CollectionOutputTransformer;
use App\Consumer\Handler\Search\IndexAllAssetsHandler;
use App\Consumer\Handler\Search\IndexAllCollectionsHandler;
use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsEventListener(event: AclUpsertEvent::NAME, method: 'onAclUpsert')]
#[AsEventListener(event: AclDeleteEvent::NAME, method: 'onAclDelete')]
readonly class AclListener
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        private ObjectMapping $objectMapping,
        private EventProducer $eventProducer,
        private TagAwareCacheInterface $collectionCache,
    ) {
    }

    public function onAclUpsert(AclUpsertEvent $event): void
    {
        $this->reIndexAsset($event->getObjectType(), $event->getObjectId());
    }

    public function onAclDelete(AclDeleteEvent $event): void
    {
        $this->reIndexAsset($event->getObjectType(), $event->getObjectId());
    }

    private function reIndexAsset(string $objectType, string $objectId = null): void
    {
        $this->collectionCache->invalidateTags([CollectionOutputTransformer::COLLECTION_CACHE_NS]);

        $objectClass = $this->objectMapping->getClassName($objectType);

        if (null === $objectId) {
            switch ($objectClass) {
                case Asset::class:
                    $this->eventProducer->publish(new EventMessage(IndexAllAssetsHandler::EVENT, []));
                    break;
                case Collection::class:
                    $this->eventProducer->publish(new EventMessage(IndexAllCollectionsHandler::EVENT, []));
                    break;
            }

            return;
        }

        if (Collection::class === $objectClass) {
            $this->eventProducer->publish(new EventMessage(IndexCollectionBranchHandler::EVENT, [
                'id' => $objectId,
            ]));
        } else {
            $this->searchIndexer->scheduleObjectsIndex($objectClass, [$objectId], SearchIndexer::ACTION_UPSERT);
        }
    }
}
