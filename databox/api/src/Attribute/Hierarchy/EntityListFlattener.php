<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Flattens the hierarchy of a list: every entity at the top level, the placeholders (empty levels) removed, the
 * attribute definitions no longer bound to hierarchy depths and the values filled by the hierarchy removed.
 *
 * Plain DQL, which the Doctrine listeners do not see: the entities and the assets are reindexed here. Synchronous,
 * so that the list is flat as soon as its flag is turned off (flagged again, it starts from a flat list).
 */
final readonly class EntityListFlattener
{
    private const int CHUNK_SIZE = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private SearchIndexer $searchIndexer,
    ) {
    }

    public function flatten(string $listId): void
    {
        $placeholderIds = $this->ids(
            'SELECT e.id FROM '.AttributeEntity::class.' e WHERE e.list = :list AND e.placeholder = true',
            ['list' => $listId],
        );
        $definitionIds = $this->ids(
            'SELECT d.id FROM '.AttributeDefinition::class.' d WHERE d.entityList = :list',
            ['list' => $listId],
        );
        $assetIds = empty($definitionIds) ? [] : $this->ids(
            'SELECT DISTINCT IDENTITY(a.asset) FROM '.Attribute::class.' a WHERE a.definition IN (:definitions) AND a.origin = :origin',
            ['definitions' => $definitionIds, 'origin' => Attribute::ORIGIN_HIERARCHY],
        );

        $this->em->wrapInTransaction(function () use ($listId, $placeholderIds, $definitionIds): void {
            $this->em->createQuery('UPDATE '.AttributeEntity::class.' e SET e.parent = NULL, e.hierarchyDepth = 0 WHERE e.list = :list')
                ->setParameter('list', $listId)
                ->execute();
            if (!empty($placeholderIds)) {
                $this->em->createQuery('DELETE FROM '.AttributeEntity::class.' e WHERE e.id IN (:ids)')
                    ->setParameter('ids', $placeholderIds)
                    ->execute();
            }
            if (!empty($definitionIds)) {
                $this->em->createQuery('UPDATE '.AttributeDefinition::class.' d SET d.entityHierarchyDepthMin = NULL, d.entityHierarchyDepthMax = NULL, d.autoFillParents = false WHERE d.id IN (:ids)')
                    ->setParameter('ids', $definitionIds)
                    ->execute();
                $this->em->createQuery('DELETE FROM '.Attribute::class.' a WHERE a.definition IN (:definitions) AND a.origin = :origin')
                    ->setParameter('definitions', $definitionIds)
                    ->setParameter('origin', Attribute::ORIGIN_HIERARCHY)
                    ->execute();
            }
        });

        foreach (array_chunk($placeholderIds, self::CHUNK_SIZE) as $chunk) {
            $this->searchIndexer->scheduleObjectsIndex(AttributeEntity::class, $chunk, Operation::Delete);
        }
        $entityIds = $this->ids('SELECT e.id FROM '.AttributeEntity::class.' e WHERE e.list = :list', ['list' => $listId]);
        foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $chunk) {
            $this->searchIndexer->scheduleObjectsIndex(AttributeEntity::class, $chunk, Operation::Upsert);
        }
        foreach (array_chunk($assetIds, self::CHUNK_SIZE) as $chunk) {
            $this->searchIndexer->scheduleObjectsIndex(Asset::class, $chunk, Operation::Upsert);
        }
    }

    /**
     * @return list<string>
     */
    private function ids(string $dql, array $parameters): array
    {
        return array_map(strval(...), $this->em->createQuery($dql)->setParameters($parameters)->getSingleColumnResult());
    }
}
