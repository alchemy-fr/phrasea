<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\ESSearchIndexer;
use App\Entity\ESIndexableInterface;
use App\Entity\SearchableEntityInterface;
use App\Entity\SearchDeleteDependencyInterface;
use App\Entity\SearchDependencyInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;

class DeferredIndexListener implements EventSubscriber
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

    public function __construct(private readonly ESSearchIndexer $searchIndexer)
    {
    }

    public function scheduleForUpdate(object $entity): void
    {
        $this->scheduledForUpdate[] = $entity;
    }

    public function flush(): void
    {
        $this->persistScheduled();
    }

    private function handlesEntity(object $entity): bool
    {
        return $entity instanceof SearchDependencyInterface
            || $entity instanceof SearchableEntityInterface
            || $this->searchIndexer->hasObjectPersisterFor(ClassUtils::getRealClass($entity::class));
    }

    /**
     * Looks for new objects that should be indexed.
     */
    public function postPersist(LifecycleEventArgs $eventArgs): void
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
    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        if (!self::$enabled) {
            return;
        }

        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            if ($this->isIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
            } else {
                $this->scheduleForDeletion($entity);
            }
        }
    }

    /**
     * Delete objects preRemove instead of postRemove so that we have access to the id.
     */
    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        if (!self::$enabled) {
            return;
        }

        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            $this->scheduleForDeletion($entity);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getEntityManager()->getUnitOfWork();

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
            ESSearchIndexer::computeObjects($objects, $this->scheduledForInsertion, ESSearchIndexer::ACTION_INSERT);
            $this->scheduledForInsertion = [];
        }
        if (!empty($this->scheduledForUpdate)) {
            ESSearchIndexer::computeObjects($objects, $this->scheduledForUpdate, ESSearchIndexer::ACTION_UPSERT);
            $this->scheduledForUpdate = [];
        }
        if (!empty($this->scheduledForDeletion)) {
            foreach ($this->scheduledForDeletion as $class => $ids) {
                if (!isset($objects[$class])) {
                    $objects[$class] = [];
                }
                $objects[$class][ESSearchIndexer::ACTION_DELETE] = $ids;
            }
            $this->scheduledForDeletion = [];
        }

        if (!empty($objects)) {
            $this->searchIndexer->scheduleIndex($objects);
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

        if ($entity instanceof SearchDeleteDependencyInterface) {
            foreach ($entity->getSearchDeleteDependencies() as $dep) {
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

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
            Events::postUpdate,
            Events::postPersist,
            Events::onFlush,
        ];
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
