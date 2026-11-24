<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\Mapper\Output\CollectionOutputMapper;
use App\Consumer\Handler\Collection\DeleteCollection;
use App\Consumer\Handler\Search\ComputeCollectionBranch;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Consumer\MessageDeduplication;
use App\Entity\Core\Collection;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::postPersist)]
#[AsDoctrineListener(Events::onFlush)]
class CollectionListener
{
    use ChangeFieldListenerTrait;

    public bool $softDeleteEnabled = true;

    /**
     * Collections soft-deleted by a removal in the current flush: the purge that
     * follows cleans their documents, there is no point in reindexing them.
     *
     * @var array<string, true>
     */
    private array $purgeScheduled = [];

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
        private readonly TagAwareCacheInterface $collectionCache,
        private readonly SearchIndexer $searchIndexer,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Collection) {
            return;
        }

        $this->collectionCache->invalidateTags([CollectionOutputMapper::COLLECTION_CACHE_NS]);

        $this->postFlushStack->addBusMessage(MessageDeduplication::coalesce(new ComputeCollectionBranch($entity->getId())));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Collection) {
            return;
        }

        $em = $args->getObjectManager();
        $accessChanged = $this->hasChangedField([
            'privacy',
            'ownerId',
            'parent',
        ], $em, $entity);
        // The trash state is inherited by the descendants and their assets
        $deletionChanged = $this->hasChangedField(['deletedAt'], $em, $entity);

        if (isset($this->purgeScheduled[$entity->getId()])) {
            unset($this->purgeScheduled[$entity->getId()]);
            $deletionChanged = false;
        }

        if (!$accessChanged && !$deletionChanged) {
            return;
        }

        $this->collectionCache->invalidateTags([CollectionOutputMapper::COLLECTION_CACHE_NS]);

        if ($accessChanged) {
            $this->postFlushStack->addBusMessage(MessageDeduplication::coalesce(new ComputeCollectionBranch($entity->getId())));
        }
        $this->postFlushStack->addBusMessage(MessageDeduplication::coalesce(new IndexCollectionBranch($entity->getId(), true)));

        if ($this->hasChangedField(['parent'], $args->getObjectManager(), $entity)) {
            // The "hasChildren" flag of the former and of the new parent
            $parentIds = array_values(array_filter(array_map(
                static fn (?Collection $parent): ?string => $parent?->getId(),
                $this->getChangedSetFor('parent', $args->getObjectManager(), $entity),
            )));
            if ([] !== $parentIds) {
                $this->postFlushStack->addCallback(fn () => $this->searchIndexer->scheduleObjectsIndex(Collection::class, $parentIds, Operation::Upsert));
            }
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->softDeleteEnabled) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Collection) {
                // Cancel direct deletion
                if (null === $entity->getDeletedAt()) {
                    $entity->setDeletedAt(new \DateTimeImmutable());
                    $this->purgeScheduled[$entity->getId()] = true;
                }
                $uow->persist($entity);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Collection::class), $entity);
                $this->postFlushStack->addBusMessage(new DeleteCollection($entity->getId()));
            }
        }
    }
}
