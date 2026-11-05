<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Places existing entities in the list hierarchy from a CSV (entity_external_id, parent_external_id, position):
 * an empty parent puts the entity at the top level.
 * Labels, translations and synonyms are never changed. Rows that cannot be applied are reported.
 */
final readonly class EntityHierarchyImporter
{
    public const string COL_ENTITY = 'entity_external_id';
    public const string COL_PARENT = 'parent_external_id';
    public const string COL_POSITION = 'position';

    private const int BATCH_SIZE = 200;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{applied: int, rejected: list<array{line: int, entity: ?string, reason: string}>}
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

        $placed = [];
        $lineOf = [];
        foreach ($rows as $line => $row) {
            $entityKey = $row[self::COL_ENTITY];
            $entityId = $byKey[$entityKey] ?? null;
            if (null === $entityId) {
                $rejected[] = ['line' => $line, 'entity' => $entityKey, 'reason' => 'Unknown entity'];
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

            if (null !== $row[self::COL_POSITION]) {
                $positionOf[$entityId] = $row[self::COL_POSITION];
            }
            $placed[$entityId] = true;
            $lineOf[$entityId] = $line;
        }

        usort($rejected, fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        if (!$dryRun) {
            $this->apply($parentOf, $this->computeDepths($parentOf), $positionOf);
        }

        return ['applied' => \count($placed), 'rejected' => $rejected];
    }

    /**
     * @param array<string, ?string> $parentOf
     * @param array<string, int>     $depthOf
     * @param array<string, int>     $positionOf
     */
    private function apply(array $parentOf, array $depthOf, array $positionOf): void
    {
        $this->em->wrapInTransaction(function () use ($parentOf, $depthOf, $positionOf): void {
            foreach (array_chunk(array_keys($parentOf), self::BATCH_SIZE) as $ids) {
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
                }
                $this->em->flush();
            }
        });
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
     * @return array<int, array{entity_external_id: string, parent_external_id: string, position: ?int}> by line number
     */
    private function parse(string $csv, array &$rejected): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        $header = array_map(fn (string $h): string => strtolower(trim($h)), str_getcsv(array_shift($lines) ?? '', escape: ''));
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
            $position = isset($columns[self::COL_POSITION]) ? trim($cells[$columns[self::COL_POSITION]] ?? '') : '';
            $rows[$i + 2] = [
                self::COL_ENTITY => trim($cells[$columns[self::COL_ENTITY]] ?? ''),
                self::COL_PARENT => isset($columns[self::COL_PARENT]) ? trim($cells[$columns[self::COL_PARENT]] ?? '') : '',
                self::COL_POSITION => is_numeric($position) ? (int) $position : null,
            ];
        }

        return $rows;
    }
}
