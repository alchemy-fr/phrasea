<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Attribute\Hierarchy\EntityListFlattener;
use App\Entity\Core\EntityList;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * A list no longer flagged as a hierarchy gets flattened, once flushed.
 */
#[AsDoctrineListener(Events::onFlush)]
#[AsDoctrineListener(Events::postFlush)]
final class EntityListHierarchyListener
{
    /**
     * @var array<string, true> IDs of the lists to flatten
     */
    private array $lists = [];

    public function __construct(
        private readonly EntityListFlattener $flattener,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof EntityList || $entity->isHierarchy()) {
                continue;
            }
            [$old] = $uow->getEntityChangeSet($entity)['options'] ?? [null];
            if (EntityList::isHierarchyOption($old)) {
                $this->lists[$entity->getId()] = true;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $lists = array_keys($this->lists);
        $this->lists = [];
        foreach ($lists as $listId) {
            $this->flattener->flatten($listId);
        }
    }
}
