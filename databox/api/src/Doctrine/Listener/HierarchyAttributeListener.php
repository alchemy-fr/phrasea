<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Hierarchy\EntityHierarchyChanged;
use App\Consumer\Handler\Hierarchy\FillAssetsHierarchy;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Keeps the "hierarchy" attribute values up to date, asynchronously:
 * - a value of a hierarchical definition written outside the batch update (upload, initial values, single
 *   attribute API...): its asset is recomputed, manual values are never overwritten;
 * - an entity moved in its list hierarchy, or a definition binding changed: the assets concerned are recomputed.
 *
 * The attribute batch update fills the hierarchy itself, synchronously (BatchAttributeManager disables this listener).
 */
#[AsDoctrineListener(Events::onFlush)]
final class HierarchyAttributeListener
{
    public bool $disabled = false;

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->disabled) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();

        $assets = [];
        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates(), ...$uow->getScheduledEntityDeletions()] as $entity) {
            if ($entity instanceof Attribute) {
                $definition = $entity->getDefinition();
                if (null !== $definition && $definition->isHierarchical() && Attribute::ORIGIN_HIERARCHY !== $entity->getOrigin() && null !== $asset = $entity->getAsset()) {
                    $assets[$definition->getWorkspaceId()][$asset->getId()] = true;
                }
            }
        }
        foreach ($assets as $workspaceId => $assetIds) {
            $this->postFlushStack->addBusMessage(new FillAssetsHierarchy($workspaceId, array_keys($assetIds)));
        }

        $moved = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof AttributeEntity) {
                $changeSet = $uow->getEntityChangeSet($entity);
                if (\array_key_exists('parent', $changeSet) || \array_key_exists('hierarchyDepth', $changeSet) || \array_key_exists('placeholder', $changeSet)) {
                    $moved[$entity->getWorkspaceId()][$entity->getListId()][] = $entity->getId();
                }
            } elseif ($entity instanceof AttributeDefinition && null !== $entity->getEntityList()) {
                $changeSet = $uow->getEntityChangeSet($entity);
                foreach (['entityHierarchyDepth', 'entityHierarchyBranch', 'autoFillParents', 'entityList', 'multiple'] as $field) {
                    if (\array_key_exists($field, $changeSet)) {
                        $this->postFlushStack->addBusMessage(new EntityHierarchyChanged($entity->getWorkspaceId(), $entity->getEntityList()->getId()));
                        break;
                    }
                }
            }
        }
        foreach ($moved as $workspaceId => $lists) {
            foreach ($lists as $listId => $entityIds) {
                $this->postFlushStack->addBusMessage(new EntityHierarchyChanged($workspaceId, $listId, $entityIds));
            }
        }
    }
}
