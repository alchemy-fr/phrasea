<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Audit\AuditableInterface;
use App\Audit\AuditIgnore;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use App\Service\Log\AuditValueNormalizer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Gedmo\SoftDeleteable\SoftDeleteableListener as GedmoSoftDeleteableListener;

/**
 * Writes the audit log of the changes the UnitOfWork sees: the auditable entities
 * (created, updated with their changed fields, deleted), the semantic changes (move,
 * substitution, trash...) and the relations rolled up to their parent object
 * (collection and basket items, profile items, asset attributes, renditions).
 *
 * Runs after the other onFlush listeners, so that the changes they make (soft deletion
 * turning a deletion into an update, attributes filled from the asset name...) are seen.
 */
#[AsDoctrineListener(Events::onFlush, priority: -100)]
#[AsDoctrineListener(GedmoSoftDeleteableListener::POST_SOFT_DELETE)]
final class AuditLogListener
{
    /**
     * Turned off by the writers that log the attribute changes themselves (BatchAttributeManager).
     */
    public static bool $trackAttributes = true;

    /**
     * Fields left out of every diff: timestamps and display order.
     */
    private const array IGNORED_FIELDS = [
        'createdAt',
        'updatedAt',
        'editedAt',
        'attributesEditedAt',
        'tagsEditedAt',
        'position',
    ];

    private const string RULE_IGNORE = 'ignore';
    private const string RULE_MASK = 'mask';

    /**
     * @var array<class-string, array<string, string>>
     */
    private array $fieldRules = [];

    public function __construct(
        private readonly AuditLogManager $auditLogManager,
    ) {
    }

    /**
     * A workspace soft deletion is turned by Gedmo into an "extra update" the changesets do not show.
     */
    public function postSoftDelete(object $args): void
    {
        $object = $args->getObject();
        if (!$object instanceof AuditableInterface || !AuditLogManager::$enabled) {
            return;
        }

        $this->auditLogManager->log(AuditLogActionEnum::ObjectTrashed, $object, inOnFlush: true);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        if (!AuditLogManager::$enabled) {
            foreach ($uow->getScheduledEntityDeletions() as $entity) {
                if ($entity instanceof AuditableInterface) {
                    $this->auditLogManager->countMutedDeletion($entity);
                }
            }

            return;
        }

        $inserted = [];
        $deleted = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $inserted[spl_object_id($entity)] = true;
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $deleted[spl_object_id($entity)] = true;
        }
        $isGone = static fn (?object $o): bool => null === $o || isset($deleted[spl_object_id($o)]);

        $collectionChanges = $this->getCollectionChanges($uow, $inserted, $deleted);

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof AuditableInterface) {
                $values = [];
                foreach ($uow->getEntityChangeSet($entity) as $field => [, $new]) {
                    $rule = $this->getFieldRule($entity, $field);
                    if (self::RULE_IGNORE !== $rule && null !== $new) {
                        $values[$field] = self::RULE_MASK === $rule ? AuditIgnore::MASK : $new;
                    }
                }

                $this->auditLogManager->log(AuditLogActionEnum::ObjectCreated, $entity, array_filter([
                    'values' => $values,
                ]), inOnFlush: true);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof AuditableInterface) {
                $this->logUpdate($uow, $entity, $collectionChanges[spl_object_id($entity)] ?? []);
                unset($collectionChanges[spl_object_id($entity)]);
            }
        }

        // Owners only changed in their collections
        foreach ($collectionChanges as $changes) {
            $this->auditLogManager->log(AuditLogActionEnum::ObjectUpdated, $changes['owner'], [
                'changes' => $changes['changes'],
            ], inOnFlush: true);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof AuditableInterface) {
                $this->auditLogManager->log(AuditLogActionEnum::ObjectDeleted, $entity, inOnFlush: true);
            }
        }

        $this->logRelations($uow, $inserted, $isGone);
    }

    private function logUpdate(UnitOfWork $uow, AuditableInterface $entity, array $collectionChanges): void
    {
        $changes = [];
        foreach ($uow->getEntityChangeSet($entity) as $field => [$old, $new]) {
            $rule = $this->getFieldRule($entity, $field);
            if (self::RULE_IGNORE === $rule || $this->logSemanticChange($entity, $field, $old, $new)) {
                continue;
            }

            $changes[$field] = self::RULE_MASK === $rule
                ? [AuditIgnore::MASK, AuditIgnore::MASK]
                : [AuditValueNormalizer::normalize($old), AuditValueNormalizer::normalize($new)];
        }

        $changes = [...$changes, ...($collectionChanges['changes'] ?? [])];
        if (empty($changes)) {
            return;
        }

        $this->auditLogManager->log(AuditLogActionEnum::ObjectUpdated, $entity, [
            'changes' => $changes,
        ], inOnFlush: true);
    }

    /**
     * @return bool whether the change was logged as a dedicated action
     */
    private function logSemanticChange(AuditableInterface $entity, string $field, mixed $old, mixed $new): bool
    {
        $action = null;
        $data = [];

        if ('deletedAt' === $field) {
            $action = null === $new ? AuditLogActionEnum::ObjectRestored : (null === $old ? AuditLogActionEnum::ObjectTrashed : null);
        } elseif ($entity instanceof Asset) {
            switch ($field) {
                case 'referenceCollection':
                    $action = AuditLogActionEnum::AssetMoved;
                    $data = [
                        'oldCollectionId' => $old?->getId(),
                        'newCollectionId' => $new?->getId(),
                    ];
                    break;
                case 'source':
                    $action = AuditLogActionEnum::AssetSubstituted;
                    $data = [
                        'oldSourceId' => $old?->getId(),
                        'newSourceId' => $new?->getId(),
                        'keepVersion' => !$entity->isNoFileVersion(),
                    ];
                    break;
                case 'status':
                    if (AssetStatusEnum::Quarantined === $old
                        && AssetStatusEnum::Accepted === $new
                        && FileAnalysisStateEnum::Bypassed === $entity->getSource()?->getAnalysisState()) {
                        $action = AuditLogActionEnum::AssetQuarantineBypassed;
                    }
                    break;
            }
        } elseif ($entity instanceof Collection && 'parent' === $field) {
            $action = AuditLogActionEnum::CollectionMoved;
            $data = [
                'oldParentId' => $old?->getId(),
                'newParentId' => $new?->getId(),
            ];
        } elseif ($entity instanceof Basket && 'archivedAt' === $field) {
            $action = null === $new ? AuditLogActionEnum::BasketUnarchived : (null === $old ? AuditLogActionEnum::BasketArchived : null);
        }

        if (null === $action) {
            return false;
        }

        $this->auditLogManager->log($action, $entity, $data, inOnFlush: true);

        return true;
    }

    /**
     * The added and removed items of the to-many associations of the auditable entities.
     *
     * @return array<int, array{owner: AuditableInterface, changes: array<string, array{added: string[], removed: string[]}>}>
     */
    private function getCollectionChanges(UnitOfWork $uow, array $inserted, array $deleted): array
    {
        $changes = [];
        $add = function (PersistentCollection $collection, array $added, array $removed) use (&$changes, $inserted, $deleted): void {
            $owner = $collection->getOwner();
            if (!$owner instanceof AuditableInterface
                || isset($inserted[spl_object_id($owner)])
                || isset($deleted[spl_object_id($owner)])) {
                return;
            }
            $field = $collection->getMapping()->fieldName;
            if (self::RULE_IGNORE === $this->getFieldRule($owner, $field)) {
                return;
            }

            $added = array_values(array_map(AuditValueNormalizer::normalize(...), $added));
            $removed = array_values(array_map(AuditValueNormalizer::normalize(...), $removed));
            if (empty($added) && empty($removed)) {
                return;
            }

            $id = spl_object_id($owner);
            $changes[$id]['owner'] = $owner;
            $changes[$id]['changes'][$field] = [
                'added' => $added,
                'removed' => $removed,
            ];
        };

        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            if ($collection->getMapping()->isManyToMany()) {
                $add($collection, $collection->getInsertDiff(), $collection->getDeleteDiff());
            }
        }
        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            if ($collection->getMapping()->isManyToMany()) {
                $add($collection, [], $collection->getSnapshot());
            }
        }

        return $changes;
    }

    /**
     * @param callable(?object): bool $isGone
     */
    private function logRelations(UnitOfWork $uow, array $inserted, callable $isGone): void
    {
        $attributeChanges = [];
        $basketChanges = [];
        $profileChanges = [];

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof CollectionAsset) {
                $asset = $entity->getAsset();
                $collection = $entity->getCollection();
                // Covered by the AssetMoved entry
                if ($asset->getReferenceCollection() === $collection
                    && (isset($inserted[spl_object_id($asset)]) || array_key_exists('referenceCollection', $uow->getEntityChangeSet($asset)))) {
                    continue;
                }

                $this->auditLogManager->log(AuditLogActionEnum::AssetAddedToCollection, $asset, [
                    'collectionId' => $collection->getId(),
                    'collectionTitle' => $collection->getName(),
                ], inOnFlush: true);
            } elseif ($entity instanceof BasketAsset) {
                $basketChanges[spl_object_id($entity->getBasket())]['basket'] = $entity->getBasket();
                $basketChanges[spl_object_id($entity->getBasket())]['added'][] = $entity->getAsset()->getId();
            } elseif ($entity instanceof ProfileItem) {
                $this->addProfileChange($profileChanges, $entity, 'add');
            } elseif ($entity instanceof Attribute && self::$trackAttributes) {
                $this->addAttributeChange($attributeChanges, $entity, [], [$entity->getValue()], $entity->getLocale());
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof ProfileItem) {
                $this->addProfileChange($profileChanges, $entity, 'update');
            } elseif ($entity instanceof Attribute && self::$trackAttributes) {
                $changeSet = $uow->getEntityChangeSet($entity);
                if (isset($changeSet['value']) || isset($changeSet['locale'])) {
                    $this->addAttributeChange(
                        $attributeChanges,
                        $entity,
                        [$changeSet['value'][0] ?? $entity->getValue()],
                        [$entity->getValue()],
                        $entity->getLocale(),
                    );
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof CollectionAsset) {
                $asset = $entity->getAsset();
                $collection = $entity->getCollection();
                if ($isGone($asset) || $isGone($collection)) {
                    continue;
                }

                $this->auditLogManager->log(AuditLogActionEnum::AssetRemovedFromCollection, $asset, [
                    'collectionId' => $collection->getId(),
                    'collectionTitle' => $collection->getName(),
                ], inOnFlush: true);
            } elseif ($entity instanceof BasketAsset) {
                if (!$isGone($entity->getBasket())) {
                    $basketChanges[spl_object_id($entity->getBasket())]['basket'] = $entity->getBasket();
                    $basketChanges[spl_object_id($entity->getBasket())]['removed'][] = $entity->getAsset()->getId();
                }
            } elseif ($entity instanceof ProfileItem) {
                $this->addProfileChange($profileChanges, $entity, 'delete');
            } elseif ($entity instanceof Attribute && self::$trackAttributes) {
                if (!$isGone($entity->getAsset())) {
                    $this->addAttributeChange($attributeChanges, $entity, [$entity->getValue()], [], $entity->getLocale());
                }
            } elseif ($entity instanceof AssetRendition) {
                if (!$isGone($entity->getAsset())) {
                    $this->auditLogManager->log(AuditLogActionEnum::RenditionDeleted, $entity->getAsset(), [
                        'renditionId' => $entity->getId(),
                        'definitionId' => $entity->getDefinition()?->getId(),
                        'definitionName' => $entity->getDefinition()?->getName(),
                    ], inOnFlush: true);
                }
            }
        }

        foreach ($basketChanges as $change) {
            if (!empty($change['added'])) {
                $this->auditLogManager->log(AuditLogActionEnum::BasketAssetsAdded, $change['basket'], [
                    'assetIds' => $change['added'],
                ], inOnFlush: true);
            }
            if (!empty($change['removed'])) {
                $this->auditLogManager->log(AuditLogActionEnum::BasketAssetsRemoved, $change['basket'], [
                    'assetIds' => $change['removed'],
                ], inOnFlush: true);
            }
        }

        foreach ($profileChanges as $change) {
            $profile = $change['profile'];
            if (isset($inserted[spl_object_id($profile)]) || $isGone($profile)) {
                continue;
            }
            $this->auditLogManager->log(AuditLogActionEnum::ProfileUpdated, $profile, [
                'items' => $change['items'],
            ], inOnFlush: true);
        }

        foreach ($attributeChanges as $change) {
            $this->auditLogManager->log(AuditLogActionEnum::AssetAttributeChanged, $change['asset'], [
                'changes' => self::mergeAttributeChanges($change['changes']),
            ], inOnFlush: true);
        }
    }

    private function addProfileChange(array &$profileChanges, ProfileItem $item, string $op): void
    {
        $profile = $item->getProfile();
        if (!$profile instanceof Profile) {
            return;
        }

        $id = spl_object_id($profile);
        $profileChanges[$id]['profile'] = $profile;
        $profileChanges[$id]['items'][] = [
            'op' => $op,
            'itemId' => $item->getId(),
            'definitionId' => $item->getDefinition()?->getId(),
            'key' => $item->getKey(),
        ];
    }

    private function addAttributeChange(array &$attributeChanges, Attribute $attribute, array $old, array $new, ?string $locale): void
    {
        $asset = $attribute->getAsset();
        $definition = $attribute->getDefinition();
        if (null === $asset || null === $definition) {
            return;
        }

        $id = spl_object_id($asset);
        $attributeChanges[$id]['asset'] = $asset;
        $attributeChanges[$id]['changes'][] = [
            'definitionId' => $definition->getId(),
            'definitionName' => $definition->getName(),
            'locale' => $locale,
            'op' => empty($old) ? ($definition->isMultiple() ? 'add' : 'set') : (empty($new) ? 'delete' : 'set'),
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * One change per definition and locale, the values of a multi-valued attribute put together.
     */
    public static function mergeAttributeChanges(array $changes): array
    {
        $merged = [];
        foreach ($changes as $change) {
            $key = $change['definitionId'].'|'.($change['locale'] ?? '');
            if (!isset($merged[$key])) {
                $merged[$key] = $change;
                continue;
            }

            $merged[$key]['old'] = [...$merged[$key]['old'], ...$change['old']];
            $merged[$key]['new'] = [...$merged[$key]['new'], ...$change['new']];
            if ($merged[$key]['op'] !== $change['op']) {
                $merged[$key]['op'] = 'set';
            }
        }

        return array_values($merged);
    }

    private function getFieldRule(object $entity, string $field): ?string
    {
        $class = $entity::class;
        if (!isset($this->fieldRules[$class])) {
            $rules = array_fill_keys(self::IGNORED_FIELDS, self::RULE_IGNORE);
            $reflection = new \ReflectionClass($entity);
            do {
                foreach ($reflection->getProperties() as $property) {
                    foreach ($property->getAttributes(AuditIgnore::class) as $attribute) {
                        $rules[$property->getName()] = $attribute->newInstance()->mask ? self::RULE_MASK : self::RULE_IGNORE;
                    }
                }
            } while ($reflection = $reflection->getParentClass());

            $this->fieldRules[$class] = $rules;
        }

        return $this->fieldRules[$class][$field] ?? null;
    }
}
