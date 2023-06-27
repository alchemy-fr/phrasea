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
    final public const EVENT_CREATE = 'create';
    final public const EVENT_UPDATE = 'update';
    final public const EVENT_DELETE = 'delete';
    private array $changes = [];
    private static bool $enabled = true;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function __construct(private readonly EntitySerializer $entitySerializer, private readonly EntityRegistry $entityRegistry, private readonly TerminateStackListener $terminateStackListener, private readonly WebhookTrigger $webhookTrigger)
    {
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
            $configNode = $this->entityRegistry->getConfigNodeForEvent($insertedEntity::class, self::EVENT_CREATE);
            if (null !== $configNode) {
                $this->addChange($configNode, $em, $insertedEntity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $updatedEntity) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent($updatedEntity::class, self::EVENT_UPDATE);
            if (null !== $configNode) {
                $entityChangeSet = $this->entitySerializer->convertChangeSetToDatabaseValue($updatedEntity::class, $uow->getEntityChangeSet($updatedEntity));
                $this->addChange($configNode, $em, $updatedEntity, $entityChangeSet);
            }
        }

        /** @var Collection $collectionUpdate */
        foreach (array_merge(
            $uow->getScheduledCollectionUpdates(),
            $uow->getScheduledCollectionDeletions()
        ) as $collectionUpdate) {
            $configNode = $this->entityRegistry->getConfigNodeForEvent($collectionUpdate->getOwner()::class, self::EVENT_UPDATE);
            if (null !== $configNode) {
                $collectionMapping = $collectionUpdate->getMapping();
                $field = $collectionMapping['fieldName'];

                $mapIds = fn(array $collection): array => array_map(fn(object $o) => $o->getId(), $collection);

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
            $configNode = $this->entityRegistry->getConfigNodeForEvent($deletedEntity::class, self::EVENT_DELETE);
            if (null !== $configNode) {
                $this->addChange($configNode, $em, $deletedEntity);
            }
        }

        $this->commitChanges();
    }

    private function snapshotEntityData(EntityManagerInterface $em, object $entity): array
    {
        $class = $entity::class;
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
