<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Doctrine\Listener;

use Alchemy\WebhookBundle\Consumer\SerializeObjectHandler;
use Alchemy\WebhookBundle\Consumer\WebhookHandler;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class EntityListener implements EventSubscriber
{
    public const EVENT_CREATE = 'create';
    public const EVENT_UPDATE = 'update';
    public const EVENT_DELETE = 'delete';

    private array $config;
    private EventProducer $eventProducer;
    private EntitySerializer $entitySerializer;

    public function __construct(
        EntitySerializer $entitySerializer,
        EventProducer $eventProducer,
        array $config
    )
    {
        $this->config = $config;
        $this->eventProducer = $eventProducer;
        $this->entitySerializer = $entitySerializer;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em  = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $insertedEntity) {
            $scalarData = $uow->getOriginalEntityData($insertedEntity);

            $this->handleCreate($insertedEntity, $scalarData);
        }

        foreach ($uow->getScheduledEntityUpdates() as $updatedEntity) {
            $scalarData = $uow->getOriginalEntityData($updatedEntity);
            $scalarData = $this->entitySerializer->convertToDatabaseValue(get_class($updatedEntity), $scalarData);
            $entityChangeSet = $uow->getEntityChangeSet($updatedEntity);

            $this->handleUpdate($updatedEntity, $scalarData, $entityChangeSet);
        }

        foreach ($uow->getScheduledEntityDeletions() as $deletedEntity) {
            $this->handleDelete($deletedEntity);
        }
    }

    private function handleUpdate(object $entity, array $data, array $changeSet): void
    {
        $configNode = $this->getConfigNodeForEvent($entity, self::EVENT_UPDATE);

        if (null !== $configNode) {
            $this->eventProducer->publish(SerializeObjectHandler::createEvent($configNode['entityClass'], $configNode['eventName'], $data, $changeSet));
        }
    }

    private function handleCreate(object $entity, array $data): void
    {
        $configNode = $this->getConfigNodeForEvent($entity, self::EVENT_CREATE);

        if (null !== $configNode) {
            $this->eventProducer->publish(SerializeObjectHandler::createEvent($configNode['entityClass'], $configNode['eventName'], $data));
        }
    }

    private function handleDelete(object $entity): void
    {
        $configNode = $this->getConfigNodeForEvent($entity, self::EVENT_DELETE);

        if (null !== $configNode) {
            $this->eventProducer->publish(WebhookHandler::createEvent($configNode['eventName'], [
                'id' => $entity->getId(),
            ]));
        }
    }

    private function getConfigNodeForEvent(object $entity, string $event): ?array
    {
        $class = get_class($entity);
        if (isset($this->config[$class])) {
            $configNode = $this->config[$class];

            if ($configNode[$event]['enabled']) {
                $configNode['eventName'] = sprintf('%s:%s', $configNode['name'], $event);
                $configNode['entityClass'] = $class;

                return $configNode;
            }
        }

        return null;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush => 'onFlush',
        ];
    }
}
