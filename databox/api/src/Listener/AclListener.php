<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use App\Consumer\Handler\Search\IndexAllAssetsHandler;
use App\Consumer\Handler\Search\IndexAllCollectionsHandler;
use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AclListener implements EventSubscriberInterface
{
    private ESSearchIndexer $searchIndexer;
    private ObjectMapping $objectMapping;
    private EventProducer $eventProducer;

    public function __construct(ESSearchIndexer $searchIndexer, ObjectMapping $objectMapping, EventProducer $eventProducer)
    {
        $this->searchIndexer = $searchIndexer;
        $this->objectMapping = $objectMapping;
        $this->eventProducer = $eventProducer;
    }

    public function onAclUpsert(AclUpsertEvent $event): void
    {
        $this->reIndexAsset($event->getObjectType(), $event->getObjectId());
    }

    public function onAclDelete(AclDeleteEvent $event): void
    {
        $this->reIndexAsset($event->getObjectType(), $event->getObjectId());
    }

    private function reIndexAsset(string $objectType, ?string $objectId = null): void
    {
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
            $this->searchIndexer->scheduleObjectsIndex($objectClass, [$objectId], ESSearchIndexer::ACTION_UPSERT);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            AclUpsertEvent::NAME => 'onAclUpsert',
            AclDeleteEvent::NAME => 'onAclDelete',
        ];
    }

}
