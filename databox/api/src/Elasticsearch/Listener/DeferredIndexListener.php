<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\ESSearchIndexer;
use App\Entity\SearchableEntityInterface;
use App\Entity\SearchDependencyInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class DeferredIndexListener implements EventSubscriber
{
    /**
     * Objects scheduled for insertion.
     */
    public array $scheduledForInsertion = [];

    /**
     * Objects scheduled to be updated or removed.
     */
    public array $scheduledForUpdate = [];

    /**
     * IDs of objects scheduled for removal.
     */
    public array $scheduledForDeletion = [];

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    public function onKernelTerminate()
    {
        $this->persistScheduled();
    }

    public function onConsoleTerminate()
    {
        $this->persistScheduled();
    }

    public function onHandlerTerminate()
    {
        $this->persistScheduled();
    }

    private function handlesEntity($entity): bool
    {
        return $entity instanceof SearchDependencyInterface
            || $entity instanceof SearchableEntityInterface;
    }

    /**
     * Looks for new objects that should be indexed.
     */
    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            $this->scheduledForInsertion[] = $entity;
        }
    }

    /**
     * Looks for objects being updated that should be indexed or removed from the index.
     */
    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            $this->scheduledForUpdate[] = $entity;
        }
    }

    /**
     * Delete objects preRemove instead of postRemove so that we have access to the id.  Because this is called
     * preRemove, first check that the entity is managed by Doctrine.
     */
    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        if ($this->handlesEntity($entity)) {
            $this->scheduleForDeletion($entity);
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
        $class = ClassUtils::getRealClass(get_class($entity));
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
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
            Events::postUpdate,
            Events::postPersist,
        ];
    }
}
