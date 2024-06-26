<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Listener;

use Alchemy\ESBundle\Indexer\ESIndexableDeleteDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableInterface;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\TransactionRollBackEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[AsDoctrineListener(Events::preRemove)]
#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::postPersist)]
#[AsDoctrineListener(Events::onFlush)]
// #[AsDoctrineListener(Events::postFlush)] TODO break tests
#[AsEventListener(KernelEvents::TERMINATE, 'flush')]
#[AsEventListener(ConsoleEvents::TERMINATE, 'flush')]
#[AsEventListener(WorkerMessageHandledEvent::class, 'flush')]
final class DeferredIndexListener
{
    private static bool $enabled = true;

    /**
     * Objects scheduled for insertion.
     */
    private array $scheduledForInsertion = [];

    /**
     * Objects scheduled to be updated or removed.
     */
    private array $scheduledForUpdate = [];

    /**
     * IDs of objects scheduled for removal.
     */
    private array $scheduledForDeletion = [];

    public function __construct(
        private readonly SearchIndexer $searchIndexer,
        private readonly Connection $connection,
    ) {
        $this->connection->getEventManager()->addEventListener(\Doctrine\DBAL\Events::onTransactionRollBack, $this);
    }

    private function handlesEntity(object $entity): bool
    {
        return $entity instanceof ESIndexableDependencyInterface
            || $entity instanceof ESIndexableInterface
            || $this->searchIndexer->hasObjectPersisterFor(ClassUtils::getRealClass($entity::class));
    }

    /**
     * Looks for new objects that should be indexed.
     */
    public function postPersist(PostPersistEventArgs $eventArgs): void
    {
        if (!self::$enabled) {
            return;
        }

        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            if ($this->isIndexable($entity)) {
                $this->scheduledForInsertion[] = $entity;
            }
        }
    }

    /**
     * Looks for objects being updated that should be indexed or removed from the index.
     */
    public function postUpdate(PostUpdateEventArgs $eventArgs): void
    {
        if (!self::$enabled) {
            return;
        }

        $entity = $eventArgs->getObject();

        $this->scheduleForUpdate($entity);
    }

    /**
     * Delete objects preRemove instead of postRemove so that we have access to the id.
     */
    public function preRemove(PreRemoveEventArgs $eventArgs): void
    {
        if (!self::$enabled) {
            return;
        }

        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            $this->scheduleForDeletion($entity);
        }
    }

    public function onTransactionRollBack(TransactionRollBackEventArgs $args): void
    {
        $this->scheduledForDeletion = [];
        $this->scheduledForInsertion = [];
        $this->scheduledForUpdate = [];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if ($em->getConnection()->getTransactionNestingLevel() > 0) {
            return;
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->persistScheduled();
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            if ($collection instanceof PersistentCollection) {
                $entity = $collection->getOwner();

                if ($this->handlesEntity($entity)) {
                    $this->scheduledForUpdate[] = $entity;
                }
            }
        }
    }

    /**
     * Persist scheduled objects to ElasticSearch
     * After persisting, clear the scheduled queue to prevent multiple data updates when using multiple flush calls.
     */
    private function persistScheduled(): void
    {
        $objects = [];

        if (!empty($this->scheduledForInsertion)) {
            SearchIndexer::computeObjects($objects, $this->scheduledForInsertion, Operation::Insert);
            $this->scheduledForInsertion = [];
        }
        if (!empty($this->scheduledForUpdate)) {
            SearchIndexer::computeObjects($objects, $this->scheduledForUpdate, Operation::Upsert);
            $this->scheduledForUpdate = [];
        }
        if (!empty($this->scheduledForDeletion)) {
            foreach ($this->scheduledForDeletion as $class => $ids) {
                if (!isset($objects[$class])) {
                    $objects[$class] = [];
                }
                $objects[$class][Operation::Delete->value] = $ids;
            }
            $this->scheduledForDeletion = [];
        }

        if (!empty($objects)) {
            $this->searchIndexer->scheduleIndex($objects);
        }
    }

    public function scheduleForUpdate(object $entity): void
    {
        if ($this->handlesEntity($entity)) {
            if ($this->isIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
            } else {
                $this->scheduleForDeletion($entity);
            }
        }
    }

    private function scheduleForDeletion(object $entity): void
    {
        $class = ClassUtils::getRealClass($entity::class);
        if (!isset($this->scheduledForDeletion[$class])) {
            $this->scheduledForDeletion[$class] = [];
        }

        if (false !== $key = array_search($entity, $this->scheduledForUpdate, true)) {
            unset($this->scheduledForUpdate[$key]);
        }
        if (false !== $key = array_search($entity, $this->scheduledForInsertion, true)) {
            unset($this->scheduledForInsertion[$key]);

            return;
        }

        $this->scheduledForDeletion[$class][] = $entity->getId();

        if ($entity instanceof ESIndexableDeleteDependencyInterface) {
            foreach ($entity->getIndexableDeleteDependencies() as $dep) {
                if (!in_array($dep, $this->scheduledForUpdate, true)) {
                    $this->scheduledForUpdate[] = $dep;
                }
            }
        }
    }

    private function isIndexable(object $object): bool
    {
        if ($object instanceof ESIndexableInterface) {
            return $object->isObjectIndexable();
        }

        return true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }
}
