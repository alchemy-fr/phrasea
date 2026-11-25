<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Service\Asset\Attribute\AttributeEntity\Importer\CsvAttributeEntityImporter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Places the entities of the list in its hierarchy from a CSV (entity_external_id, parent_external_id):
 * an empty parent puts the entity at the top level. The children of a parent take the order of the file,
 * before those the file does not place there. An unknown entity is created, its key becoming its external ID
 * (a value is then required, or the placeholder column at 1 for an empty level).
 * The columns of the values CSV (CsvAttributeEntityImporter: value, emoji, color, status, translation_<locale>)
 * update the entity when present; an empty value keeps it, an empty translation removes it, the locales without
 * column are kept. The
 * synonyms are never changed; placeholder is only read on a creation.
 * Rows that cannot be applied (invalid values included) are reported.
 */
final readonly class EntityHierarchyImporter
{
    public const string COL_ENTITY = 'entity_external_id';
    public const string COL_PARENT = 'parent_external_id';
    /** Columns of the values CSV, applied to the entity */
    private const array FIELDS = ['value', 'emoji', 'color', 'status'];

    private const int BATCH_SIZE = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array{applied: int, created: int, rejected: list<array{line: int, entity: ?string, reason: string}>}
     */
    public function import(EntityList $list, string $csv, bool $dryRun): array
    {
        $rejected = [];
        $rows = $this->parse($csv, $rejected);

        // Every entity of the list: id => [externalId, parentId]
        $entities = $this->em->createQueryBuilder()
            ->select('e.id', 'e.externalId', 'IDENTITY(e.parent) AS parentId', 'e.placeholder', 'e.position')
            ->from(AttributeEntity::class, 'e')
            ->andWhere('e.list = :list')
            ->setParameter('list', $list->getId())
            ->getQuery()
            ->getArrayResult();

        $byKey = [];
        $parentOf = [];
        $positionOf = [];
        foreach ($entities as $e) {
            $id = (string) $e['id'];
            $byKey[$id] = $id;
            if (null !== $e['externalId'] && '' !== $e['externalId']) {
                $byKey[$e['externalId']] = $id;
            }
            $parentOf[$id] = null !== $e['parentId'] ? (string) $e['parentId'] : null;
            $positionOf[$id] = (int) $e['position'];
        }

        // The first row of each unknown key creates it
        $newLines = [];
        foreach ($rows as $line => $row) {
            $key = $row[self::COL_ENTITY];
            if ('' !== $key && !isset($byKey[$key]) && !\in_array($key, $newLines, true)) {
                $newLines[$line] = $key;
            }
        }

        $invalid = $this->checkFields($list, $rows, $byKey, $newLines);

        /** @var array<string, int> $new id => line, of the entities to create */
        $new = [];
        foreach ($newLines as $line => $key) {
            if (!isset($invalid[$line])) {
                $id = Uuid::uuid4()->toString();
                $byKey[$key] = $id;
                $parentOf[$id] = null;
                $positionOf[$id] = \PHP_INT_MAX;
                $new[$id] = $line;
            }
        }
        $previousParentOf = $parentOf;

        $placed = [];
        $lineOf = [];
        $fieldsOf = [];
        foreach ($rows as $line => $row) {
            $entityKey = $row[self::COL_ENTITY];
            if (isset($invalid[$line])) {
                $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => $invalid[$line]];
                continue;
            }
            $entityId = $byKey[$entityKey] ?? null;
            if (null === $entityId) {
                // No key, or its creating row was rejected
                $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => '' === $entityKey ? 'Missing entity' : 'Unknown entity'];
                continue;
            }
            if (isset($placed[$entityId])) {
                $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => sprintf('Entity already placed on line %d', $lineOf[$entityId])];
                continue;
            }

            $parentKey = $row[self::COL_PARENT];
            if ('' === $parentKey) {
                $parentOf[$entityId] = null;
            } else {
                $parentId = $byKey[$parentKey] ?? null;
                if (null === $parentId) {
                    $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => sprintf('Unknown parent "%s"', $parentKey)];
                    continue;
                }
                if ($parentId === $entityId) {
                    $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => 'An entity cannot be its own parent'];
                    continue;
                }
                $previousParent = $parentOf[$entityId];
                $parentOf[$entityId] = $parentId;
                if ($this->hasCycle($entityId, $parentOf)) {
                    $parentOf[$entityId] = $previousParent;
                    $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => 'Cycle in the hierarchy'];
                    continue;
                }
            }

            $placed[$entityId] = true;
            $lineOf[$entityId] = $line;
            if ([] !== $row['fields']) {
                $fieldsOf[$entityId] = $row['fields'];
            }
        }

        // An entity to create whose row was rejected (unknown parent, cycle) is not created: the rows placing
        // entities under it are rejected in turn
        do {
            $dropped = array_diff_key($new, $placed);
            foreach (array_keys($dropped) as $id) {
                unset($new[$id], $parentOf[$id], $positionOf[$id]);
            }
            foreach ($placed as $id => $_) {
                $parentId = $parentOf[$id];
                if (null !== $parentId && isset($dropped[$parentId])) {
                    $rejected[] = ['line' => $lineOf[$id], 'entity' => $rows[$lineOf[$id]][self::COL_ENTITY], 'reason' => sprintf('Parent "%s" not created', $rows[$dropped[$parentId]][self::COL_ENTITY])];
                    unset($placed[$id], $fieldsOf[$id]);
                    if (!isset($new[$id])) {
                        $parentOf[$id] = $previousParentOf[$id];
                    }
                }
            }
        } while ([] !== $dropped);

        $positionOf = $this->orderSiblings(array_keys($placed), $parentOf, $positionOf);

        usort($rejected, fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        if (!$dryRun) {
            $newKeys = array_map(fn (int $line): string => $rows[$line][self::COL_ENTITY], $new);
            $placeholders = array_filter($new, fn (int $line): bool => $rows[$line]['placeholder']);
            $this->apply($list->getId(), $parentOf, $this->computeDepths($parentOf), $positionOf, $fieldsOf, $newKeys, $placeholders);
        }

        return ['applied' => \count($placed), 'created' => \count($new), 'rejected' => $rejected];
    }

    /**
     * @param array<string, ?string> $parentOf
     * @param array<string, int>     $depthOf
     * @param array<string, int>     $positionOf
     * @param array<string, array>   $fieldsOf
     * @param array<string, string>  $newKeys      id => external ID, of the entities to create
     * @param array<string, mixed>   $placeholders the entities to create as placeholders, by ID
     */
    private function apply(string $listId, array $parentOf, array $depthOf, array $positionOf, array $fieldsOf, array $newKeys, array $placeholders): void
    {
        $this->em->wrapInTransaction(function () use ($listId, $parentOf, $depthOf, $positionOf, $fieldsOf, $newKeys, $placeholders): void {
            // The new entities first, from the top: each parent exists before its children
            $newIds = array_keys($newKeys);
            usort($newIds, fn (string $a, string $b): int => ($depthOf[$a] ?? 0) <=> ($depthOf[$b] ?? 0));
            foreach (array_chunk($newIds, self::BATCH_SIZE) as $ids) {
                $list = $this->em->getReference(EntityList::class, $listId);
                foreach ($ids as $id) {
                    $entity = new AttributeEntity($id);
                    $entity->setList($list);
                    $entity->setExternalId($newKeys[$id]);
                    self::initNew($entity, $fieldsOf[$id] ?? [], isset($placeholders[$id]));
                    $parentId = $parentOf[$id];
                    $entity->setParent(null !== $parentId ? $this->em->getReference(AttributeEntity::class, $parentId) : null);
                    $entity->setHierarchyDepth($depthOf[$id] ?? 0);
                    $entity->setPosition($positionOf[$id] ?? 0);
                    $this->em->persist($entity);
                }
                $this->em->flush();
            }

            foreach (array_chunk(array_keys(array_diff_key($parentOf, $newKeys)), self::BATCH_SIZE) as $ids) {
                /** @var AttributeEntity[] $entities */
                $entities = $this->em->getRepository(AttributeEntity::class)->findBy(['id' => $ids]);
                foreach ($entities as $entity) {
                    $id = $entity->getId();
                    $parentId = $parentOf[$id];
                    $parent = null !== $parentId ? $this->em->getReference(AttributeEntity::class, $parentId) : null;
                    if ($entity->getParentId() !== $parentId) {
                        $entity->setParent($parent);
                    }
                    $entity->setHierarchyDepth($depthOf[$id] ?? 0);
                    $entity->setPosition($positionOf[$id] ?? 0);
                    if (isset($fieldsOf[$id])) {
                        self::applyFields($entity, $fieldsOf[$id]);
                    }
                }
                $this->em->flush();
            }
        });
    }

    /**
     * Applies the columns of each row to its entity, or to the entity it creates, and validates it, without
     * saving: the reason of the invalid rows, by line.
     *
     * @param array<int, array{entity_external_id: string, parent_external_id: string, placeholder: bool, fields: array}> $rows
     * @param array<string, string>                                                                                       $byKey
     * @param array<int, string>                                                                                          $newLines the key to create, by line
     *
     * @return array<int, string>
     */
    private function checkFields(EntityList $list, array $rows, array $byKey, array $newLines): array
    {
        $invalid = [];
        $toCheck = [];
        foreach ($rows as $line => $row) {
            $entityId = $byKey[$row[self::COL_ENTITY]] ?? null;
            if ((null === $entityId && !isset($newLines[$line])) || (null !== $entityId && [] === $row['fields'])) {
                continue;
            }
            $status = $row['fields']['status'] ?? '';
            if ('' !== $status && !\in_array($status, array_map('strval', AttributeEntity::STATUS_CHOICES), true)) {
                $invalid[$line] = sprintf('Invalid status "%s"', $status);
                continue;
            }
            if (null === $entityId) {
                // Before any clear: the list is still managed
                $entity = new AttributeEntity();
                $entity->setList($list);
                self::initNew($entity, $row['fields'], $row['placeholder']);
                if (null !== $reason = $this->violations($entity)) {
                    $invalid[$line] = $reason;
                }
                continue;
            }
            $toCheck[$line] = $entityId;
        }

        foreach (array_chunk($toCheck, self::BATCH_SIZE, true) as $chunk) {
            $entities = [];
            foreach ($this->em->getRepository(AttributeEntity::class)->findBy(['id' => array_values($chunk)]) as $entity) {
                $entities[$entity->getId()] = $entity;
            }
            foreach ($chunk as $line => $entityId) {
                $entity = $entities[$entityId] ?? null;
                if (null === $entity) {
                    continue;
                }
                self::applyFields($entity, $rows[$line]['fields']);
                if (null !== $reason = $this->violations($entity)) {
                    $invalid[$line] = $reason;
                }
            }
            // Nothing is saved here: apply() loads them again
            $this->em->clear();
        }

        return $invalid;
    }

    private function violations(AttributeEntity $entity): ?string
    {
        $messages = [];
        foreach ($this->validator->validate($entity) as $violation) {
            $messages[] = $violation->getPropertyPath().': '.$violation->getMessage();
        }

        return [] !== $messages ? implode(' ', $messages) : null;
    }

    /**
     * A new entity: its columns, and the value of an empty level when none is given.
     */
    private static function initNew(AttributeEntity $entity, array $fields, bool $placeholder): void
    {
        self::applyFields($entity, $fields);
        if ($placeholder) {
            $entity->setPlaceholder(true);
            if ('' === (string) $entity->getValue()) {
                $entity->setValue('—');
            }
        }
    }

    /**
     * @param array{value?: string, emoji?: string, color?: string, status?: string, translations?: array<string, string>} $fields
     */
    private static function applyFields(AttributeEntity $entity, array $fields): void
    {
        // Never empty: an empty cell keeps it (and fails a creation)
        if ('' !== ($fields['value'] ?? '')) {
            $entity->setValue($fields['value']);
        }
        if (\array_key_exists('emoji', $fields)) {
            $entity->setEmoji('' !== $fields['emoji'] ? $fields['emoji'] : null);
        }
        if (\array_key_exists('color', $fields)) {
            $entity->setColor('' !== $fields['color'] ? $fields['color'] : null);
        }
        if ('' !== ($fields['status'] ?? '')) {
            $entity->setStatus((int) $fields['status']);
        }
        if (isset($fields['translations'])) {
            $translations = $entity->getTranslations() ?? [];
            foreach ($fields['translations'] as $locale => $translation) {
                if ('' === $translation) {
                    unset($translations[$locale]);
                } else {
                    $translations[$locale] = $translation;
                }
            }
            $entity->setTranslations([] !== $translations ? $translations : null);
        }
    }

    /**
     * The entities placed by the file first among their siblings, in the order of the file; then the
     * siblings it does not place, in their former order.
     *
     * @param list<string>           $placed     in the order of the file
     * @param array<string, ?string> $parentOf
     * @param array<string, int>     $positionOf
     *
     * @return array<string, int>
     */
    private function orderSiblings(array $placed, array $parentOf, array $positionOf): array
    {
        $listed = [];
        foreach ($placed as $id) {
            $listed[$parentOf[$id] ?? ''][] = $id;
        }
        $isPlaced = array_flip($placed);
        $others = [];
        foreach ($parentOf as $id => $parentId) {
            if (!isset($isPlaced[$id]) && isset($listed[$parentId ?? ''])) {
                $others[$parentId ?? ''][] = $id;
            }
        }

        foreach ($listed as $parentKey => $ids) {
            $rest = $others[$parentKey] ?? [];
            usort($rest, fn (string $a, string $b): int => $positionOf[$a] <=> $positionOf[$b]);
            foreach ([...$ids, ...$rest] as $position => $id) {
                $positionOf[$id] = $position;
            }
        }

        return $positionOf;
    }

    /**
     * @param array<string, ?string> $parentOf
     */
    private function hasCycle(string $id, array $parentOf): bool
    {
        $seen = [$id => true];
        $current = $parentOf[$id] ?? null;
        while (null !== $current) {
            if (isset($seen[$current])) {
                return true;
            }
            $seen[$current] = true;
            $current = $parentOf[$current] ?? null;
        }

        return false;
    }

    /**
     * @param array<string, ?string> $parentOf
     *
     * @return array<string, int>
     */
    private function computeDepths(array $parentOf): array
    {
        $children = [];
        $depthOf = [];
        $queue = [];
        foreach ($parentOf as $id => $parentId) {
            if (null !== $parentId) {
                $children[$parentId][] = $id;
            } else {
                $depthOf[$id] = 0;
                $queue[] = $id;
            }
        }

        while (!empty($queue)) {
            $id = array_shift($queue);
            foreach ($children[$id] ?? [] as $child) {
                $depthOf[$child] = $depthOf[$id] + 1;
                $queue[] = $child;
            }
        }

        return $depthOf;
    }

    /**
     * @param list<array{line: int, entity: ?string, reason: string}> $rejected
     *
     * @return array<int, array{entity_external_id: string, parent_external_id: string, placeholder: bool, fields: array}> by line number
     */
    private function parse(string $csv, array &$rejected): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        // Case insensitive, but the locale of a translation column
        $prefix = CsvAttributeEntityImporter::TRANSLATION_PREFIX;
        $header = array_map(fn (string $h): string => str_starts_with(strtolower(trim($h)), $prefix)
            ? $prefix.substr(trim($h), \strlen($prefix))
            : strtolower(trim($h)), str_getcsv(array_shift($lines) ?? '', escape: ''));
        $columns = array_flip($header);
        if (!isset($columns[self::COL_ENTITY])) {
            $rejected[] = ['line' => 1, 'entity' => null, 'reason' => sprintf('Missing "%s" column', self::COL_ENTITY)];

            return [];
        }

        $rows = [];
        foreach ($lines as $i => $line) {
            if ('' === trim($line)) {
                continue;
            }
            $cells = str_getcsv($line, escape: '');
            $fields = [];
            foreach ($columns as $header => $index) {
                $cell = trim($cells[$index] ?? '');
                if (\in_array($header, self::FIELDS, true)) {
                    $fields[$header] = $cell;
                } elseif (str_starts_with((string) $header, CsvAttributeEntityImporter::TRANSLATION_PREFIX)) {
                    $fields['translations'][substr((string) $header, \strlen(CsvAttributeEntityImporter::TRANSLATION_PREFIX))] = $cell;
                }
            }
            $rows[$i + 2] = [
                self::COL_ENTITY => trim($cells[$columns[self::COL_ENTITY]] ?? ''),
                self::COL_PARENT => isset($columns[self::COL_PARENT]) ? trim($cells[$columns[self::COL_PARENT]] ?? '') : '',
                // Only read for an entity to create
                'placeholder' => isset($columns['placeholder']) && \in_array(strtolower(trim($cells[$columns['placeholder']] ?? '')), ['1', 'true', 'yes'], true),
                'fields' => $fields,
            ];
        }

        return $rows;
    }
}
