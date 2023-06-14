<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Doctrine\Listener;

use Alchemy\WebhookBundle\Config\EntityRegistry;
use Alchemy\WebhookBundle\Consumer\SerializeObjectHandler;
use Alchemy\WebhookBundle\Consumer\WebhookHandler;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Alchemy\WebhookBundle\Listener\TerminateStackListener;
use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class EntityListener implements EventSubscriber
{
    public const EVENT_CREATE = 'create';
    public const EVENT_UPDATE = 'update';
    public const EVENT_DELETE = 'delete';

    private EntitySerializer $entitySerializer;
    private EntityRegistry $entityRegistry;
    private array $changes = [];
    private TerminateStackListener $terminateStackListener;
    private WebhookTrigger $webhookTrigger;
    private static bool $enabled = true;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function __construct(
        EntitySerializer $entitySerializer,
        EntityRegistry $entityRegistry,
        TerminateStackListener $terminateStackListener,
        WebhookTrigger $webhookTrigger
    ) {
        $this->entitySerializer = $entitySerializer;
        $this->entityRegistry = $entityRegistry;
        $this->terminateStackListener = $terminateStackListener;
        $this->webhookTrigger = $webhookTrigger;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!self::$enabled || !$this->webhookTrigger->hasWebhooks()) {
            return;
        }

        $this->changes = [];
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $insertedEntity) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent(get_class($insertedEntity), self::EVENT_CREATE);
            if (null !== $configNode) {
                $this->addChange($configNode, $em, $insertedEntity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $updatedEntity) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent(get_class($updatedEntity), self::EVENT_UPDATE);
            if (null !== $configNode) {
                $entityChangeSet = $this->entitySerializer->convertChangeSetToDatabaseValue(get_class($updatedEntity), $uow->getEntityChangeSet($updatedEntity));
                $this->addChange($configNode, $em, $updatedEntity, $entityChangeSet);
            }
        }

        /** @var Collection $collectionUpdate */
        foreach (array_merge(
            $uow->getScheduledCollectionUpdates(),
            $uow->getScheduledCollectionDeletions()
        ) as $collectionUpdate) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent(get_class($collectionUpdate->getOwner()), self::EVENT_UPDATE);
            if (null !== $configNode) {
                $collectionMapping = $collectionUpdate->getMapping();
                $field = $collectionMapping['fieldName'];

                $mapIds = function (array $collection): array {
                    return array_map(function (object $o) {
                        return $o->getId();
                    }, $collection);
                };

                $old = $collectionUpdate->getSnapshot();
                $new = $collectionUpdate->unwrap()->toArray();

                $this->addChange($configNode, $em, $collectionUpdate->getOwner(), [
                    $field => [
                        $mapIds($old),
                        $mapIds($new),
                    ],
                ]);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $deletedEntity) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent(get_class($deletedEntity), self::EVENT_DELETE);
            if (null !== $configNode) {
                $this->addChange($configNode, $em, $deletedEntity);
            }
        }

        $this->commitChanges();
    }

    private function snapshotEntityData(EntityManagerInterface $em, object $entity): array
    {
        $class = get_class($entity);
        $data = $em->getUnitOfWork()->getOriginalEntityData($entity);

        return $this->entitySerializer->convertToDatabaseValue($class, $data);
    }

    private function addChange(array $configNode, EntityManagerInterface $em, object $entity, array $changeSet = null): void
    {
        $event = $configNode['event'];
        $oid = spl_object_id($entity);

        if (self::EVENT_UPDATE === $event && isset($this->changes[self::EVENT_CREATE][$oid])) {
            return;
        }

        if (null !== $changeSet && !empty($configNode['ignoreProperties'])) {
            if (empty(array_diff(array_keys($changeSet), $configNode['ignoreProperties']))) {
                return;
            }
        }

        $node = $this->changes[$event][$oid] ?? [
            'config' => $configNode,
        ];

        if (self::EVENT_DELETE === $event) {
            $node['data'] = $this->entitySerializer->getEntityIdentifier($entity);
        } elseif (!isset($node['data'])) {
            $node['data'] = $this->snapshotEntityData($em, $entity);
        }

        if (null !== $changeSet) {
            $node['changeSet'] = array_merge(
                $node['changeSet'] ?? [],
                $changeSet
            );
        }
        $this->changes[$event][$oid] = $node;
    }

    private function commitChanges(): void
    {
        $changes = $this->changes;
        $this->changes = [];
        foreach ($changes as $event => $entities) {
            foreach ($entities as $change) {
                $configNode = $change['config'];
                $data = $change['data'];
                switch ($event) {
                    case self::EVENT_DELETE:
                        $this->terminateStackListener->addEvent(WebhookHandler::createEvent($configNode['eventName'], [
                            'id' => $data['id'],
                        ]));
                        break;
                    case self::EVENT_UPDATE:
                        $this->terminateStackListener->addEvent(SerializeObjectHandler::createEvent($configNode['entityClass'], $configNode['eventName'], $data, $change['changeSet']));
                        break;
                    case self::EVENT_CREATE:
                        $this->terminateStackListener->addEvent(SerializeObjectHandler::createEvent($configNode['entityClass'], $configNode['eventName'], $data));
                        break;
                }
            }
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush => 'onFlush',
        ];
    }
}
