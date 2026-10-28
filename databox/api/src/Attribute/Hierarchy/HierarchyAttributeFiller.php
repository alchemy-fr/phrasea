<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Upward filling of the attributes bound to an entity list hierarchy: a value (source) of a definition having
 * "autoFillParents" fills the definitions bound to the depths of its ancestors (Ville=Paris fills Pays=France).
 *
 * - Filled values get the "hierarchy" origin; they follow the hierarchy (added, updated, removed).
 * - Any other value is never overwritten silently: a single-valued definition holding another value than the
 *   expected ancestor is a conflict, rejected ("reject") or resolved in favor of the values written by the
 *   current operation ("replace"). A recompute (hierarchy change) keeps them and reports the conflict.
 */
final class HierarchyAttributeFiller
{
    public const string CONFLICT_REJECT = 'reject';
    public const string CONFLICT_REPLACE = 'replace';
    public const array CONFLICT_STRATEGIES = [self::CONFLICT_REJECT, self::CONFLICT_REPLACE];

    private const int MAX_RESOLUTION_PASSES = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeDefinitionRepository $definitionRepository,
    ) {
    }

    /**
     * Whether one of the definitions takes part in a hierarchy (null: any definition may have changed).
     *
     * @param string[]|null $definitionIds
     */
    public function isConcerned(string $workspaceId, ?array $definitionIds): bool
    {
        $definitions = $this->definitionRepository->getHierarchicalDefinitions($workspaceId);
        if (null === $definitionIds) {
            return !empty($definitions);
        }

        return !empty(array_intersect($definitionIds, array_map(fn (AttributeDefinition $d): string => $d->getId(), $definitions)));
    }

    /**
     * To be called once the written values are flushed. Changes are persisted, not flushed.
     *
     * @param string[] $assetIds
     * @param string[] $touchedDefinitionIds definitions written by the current operation: they win on "replace"
     * @param bool     $recompute            hierarchy change: conflicts are reported, never thrown nor resolved
     *
     * @throws ValidationException on a conflict left by the strategy (see HierarchyConflictViolations)
     */
    public function fill(
        string $workspaceId,
        array $assetIds,
        array $touchedDefinitionIds = [],
        string $strategy = self::CONFLICT_REJECT,
        bool $recompute = false,
        ?string $listId = null,
    ): HierarchyFillReport {
        $report = new HierarchyFillReport();
        $definitions = $this->definitionRepository->getHierarchicalDefinitions($workspaceId, $listId);
        if (empty($definitions) || empty($assetIds)) {
            return $report;
        }

        $byList = [];
        foreach ($definitions as $definition) {
            $byList[$definition->getEntityList()->getId()][] = $definition;
        }

        /** @var Attribute[] $attributes */
        $attributes = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Attribute::class, 'a')
            ->andWhere('a.asset IN (:assets)')
            ->andWhere('a.definition IN (:definitions)')
            ->setParameter('assets', $assetIds)
            ->setParameter('definitions', array_map(fn (AttributeDefinition $d): string => $d->getId(), $definitions))
            ->addOrderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($attributes as $attribute) {
            $grouped[$attribute->getAsset()->getId()][$attribute->getDefinition()->getEntityList()->getId()][$attribute->getLocale() ?? ''][] = $attribute;
        }

        $touched = array_fill_keys($touchedDefinitionIds, true);
        foreach ($assetIds as $assetId) {
            foreach ($byList as $lId => $listDefinitions) {
                foreach ($grouped[$assetId][$lId] ?? ['' => []] as $locale => $localeAttributes) {
                    $this->fillGroup($assetId, '' === $locale ? null : $locale, $listDefinitions, $localeAttributes, $touched, $strategy, $recompute, $report);
                }
            }
        }

        if ($report->hasConflicts() && !$recompute) {
            throw HierarchyConflictViolations::createException($report->getConflicts());
        }

        return $report;
    }

    /**
     * @param AttributeDefinition[] $definitions of the same list
     * @param Attribute[]           $attributes  of the asset, list and locale
     * @param array<string, true>   $touched
     */
    private function fillGroup(
        string $assetId,
        ?string $locale,
        array $definitions,
        array $attributes,
        array $touched,
        string $strategy,
        bool $recompute,
        HierarchyFillReport $report,
    ): void {
        $attributes = array_values($attributes);
        $skipped = [];

        for ($pass = 0; $pass < self::MAX_RESOLUTION_PASSES; ++$pass) {
            $expected = $this->computeExpected($definitions, $attributes);
            $conflicts = $this->findConflicts($definitions, $attributes, $expected, $skipped);
            if (empty($conflicts)) {
                break;
            }

            $removed = false;
            foreach ($conflicts as $conflict) {
                $definitionId = $conflict->definition->getId();
                if ($recompute) {
                    // The manual values are kept: the definition is left as is
                    $skipped[$definitionId] = true;
                    $report->addConflict($assetId, $conflict);
                    continue;
                }

                $toRemove = self::CONFLICT_REPLACE === $strategy ? $this->resolve($conflict, $touched) : null;
                if (null === $toRemove) {
                    $skipped[$definitionId] = true;
                    $report->addConflict($assetId, $conflict);
                    continue;
                }

                foreach ($toRemove as $attribute) {
                    $this->em->remove($attribute);
                    $attributes = array_values(array_filter($attributes, fn (Attribute $a): bool => $a !== $attribute));
                    $report->addRemoved($assetId, $attribute);
                }
                $removed = true;
            }

            if (!$removed) {
                break;
            }
        }

        $expected = $this->computeExpected($definitions, $attributes);
        foreach ($definitions as $definition) {
            if (!isset($skipped[$definition->getId()])) {
                $this->sync($assetId, $locale, $definition, $attributes, $expected[$definition->getId()] ?? [], $report);
            }
        }
    }

    /**
     * Ancestor entities expected per definition, with the sources implying them.
     *
     * @param AttributeDefinition[] $definitions
     * @param Attribute[]           $attributes
     *
     * @return array<string, array<string, array{entity: AttributeEntity, sources: Attribute[]}>> definition ID => entity ID => ...
     */
    private function computeExpected(array $definitions, array $attributes): array
    {
        $byDepth = [];
        foreach ($definitions as $definition) {
            $byDepth[$definition->getEntityHierarchyDepth()][] = $definition;
        }

        $expected = [];
        foreach ($attributes as $source) {
            $sourceDefinition = $source->getDefinition();
            if (Attribute::ORIGIN_HIERARCHY === $source->getOrigin() || !$sourceDefinition->isAutoFillParents()) {
                continue;
            }
            $entity = $this->findEntity($source->getValue());
            if (null === $entity) {
                continue;
            }

            $ancestor = $entity->getParent();
            while (null !== $ancestor) {
                if (!$ancestor->isPlaceholder() && null !== $ancestor->getHierarchyDepth()) {
                    foreach ($byDepth[$ancestor->getHierarchyDepth()] ?? [] as $target) {
                        if ($target === $sourceDefinition || !$this->isInBranch($ancestor, $target)) {
                            continue;
                        }
                        $expected[$target->getId()][$ancestor->getId()]['entity'] = $ancestor;
                        $expected[$target->getId()][$ancestor->getId()]['sources'][] = $source;
                    }
                }
                $ancestor = $ancestor->getParent();
            }
        }

        return $expected;
    }

    /**
     * @param AttributeDefinition[] $definitions
     * @param Attribute[]           $attributes
     * @param array<string, true>   $skipped
     *
     * @return HierarchyConflict[]
     */
    private function findConflicts(array $definitions, array $attributes, array $expected, array $skipped): array
    {
        $conflicts = [];
        foreach ($definitions as $definition) {
            $definitionId = $definition->getId();
            if ($definition->isMultiple() || isset($skipped[$definitionId]) || empty($expected[$definitionId])) {
                continue;
            }

            $sources = array_merge(...array_column($expected[$definitionId], 'sources'));
            $entities = array_column($expected[$definitionId], 'entity');
            if (\count($entities) > 1) {
                // Paris and Rome in a multi-valued city: a single-valued country cannot hold both
                $conflicts[] = new HierarchyConflict($definition, null, $entities, $sources, resolvable: false);
                continue;
            }

            foreach ($this->getAttributesOf($attributes, $definition) as $existing) {
                if (Attribute::ORIGIN_HIERARCHY !== $existing->getOrigin() && !isset($expected[$definitionId][$existing->getValue()])) {
                    $conflicts[] = new HierarchyConflict($definition, $existing, $entities, $sources);
                }
            }
        }

        return $conflicts;
    }

    /**
     * The values to remove for the operation to win, null when it cannot.
     *
     * @param array<string, true> $touched
     *
     * @return Attribute[]|null
     */
    private function resolve(HierarchyConflict $conflict, array $touched): ?array
    {
        if (!$conflict->resolvable || null === $conflict->current) {
            return null;
        }

        $currentTouched = isset($touched[$conflict->definition->getId()]);
        $untouchedSources = array_filter($conflict->sources, fn (Attribute $s): bool => !isset($touched[$s->getDefinition()->getId()]));
        $sourcesTouched = \count($untouchedSources) < \count($conflict->sources);

        if ($currentTouched && !$sourcesTouched) {
            // France replaced by Italie: Paris and Île-de-France no longer fit
            $sources = [];
            foreach ($conflict->sources as $source) {
                $sources[spl_object_id($source)] = $source;
            }

            return array_values($sources);
        }
        if (!$currentTouched && $sourcesTouched) {
            if (!empty($untouchedSources)) {
                // Other existing sources disagree with the new value as well
                return null;
            }

            // Paris selected while Pays holds Belgique: Belgique is replaced
            return [$conflict->current];
        }

        return null;
    }

    /**
     * Brings the "hierarchy" values of the definition to the expected ancestors.
     *
     * @param Attribute[]                                                         $attributes
     * @param array<string, array{entity: AttributeEntity, sources: Attribute[]}> $expected
     */
    private function sync(string $assetId, ?string $locale, AttributeDefinition $definition, array $attributes, array $expected, HierarchyFillReport $report): void
    {
        $existing = $this->getAttributesOf($attributes, $definition);
        $manualValues = [];
        $filled = [];
        foreach ($existing as $attribute) {
            if (Attribute::ORIGIN_HIERARCHY === $attribute->getOrigin()) {
                $filled[] = $attribute;
            } else {
                $manualValues[$attribute->getValue()] = true;
            }
        }

        $missing = array_diff_key($expected, $manualValues);
        if (!$definition->isMultiple() && !empty($manualValues)) {
            $missing = [];
        }

        foreach ($filled as $attribute) {
            if (isset($missing[$attribute->getValue()])) {
                unset($missing[$attribute->getValue()]);
                continue;
            }

            // A single-valued definition reuses its filled value
            if (!$definition->isMultiple() && 1 === \count($missing)) {
                $entityId = (string) array_key_first($missing);
                $attribute->setValue($entityId);
                $this->em->persist($attribute);
                $report->addFilled($assetId, $attribute);
                $missing = [];
                continue;
            }

            $this->em->remove($attribute);
            $report->addRemoved($assetId, $attribute);
        }

        if (!$definition->isMultiple() && \count($missing) > 1) {
            return;
        }

        $position = \count($existing);
        foreach ($missing as $entityId => $item) {
            $attribute = new Attribute();
            $attribute->setAsset($this->em->getReference(Asset::class, $assetId));
            $attribute->setDefinition($definition);
            $attribute->setLocale($locale);
            $attribute->setOrigin(Attribute::ORIGIN_HIERARCHY);
            $attribute->setValue((string) $entityId);
            $attribute->setPosition($position++);
            $this->em->persist($attribute);
            $report->addFilled($assetId, $attribute);
        }
    }

    /**
     * @param Attribute[] $attributes
     *
     * @return Attribute[]
     */
    private function getAttributesOf(array $attributes, AttributeDefinition $definition): array
    {
        return array_values(array_filter($attributes, fn (Attribute $a): bool => $a->getDefinition() === $definition));
    }

    private function isInBranch(AttributeEntity $entity, AttributeDefinition $definition): bool
    {
        $branch = $definition->getEntityHierarchyBranch();
        if (null === $branch) {
            return true;
        }

        return \in_array($branch->getId(), $entity->getAncestorIds(), true);
    }

    private function findEntity(?string $id): ?AttributeEntity
    {
        if (null === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $this->em->find(AttributeEntity::class, $id);
    }
}
