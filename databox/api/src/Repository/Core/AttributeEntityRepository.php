<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttributeEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AttributeEntityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AttributeEntity::class);
    }

    public function getESQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('t')
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC')
        ;
    }

    /**
     * IDs of the entities below the given ones in the hierarchy (the given ones included when $includeSelf).
     *
     * @param string[] $ids
     *
     * @return string[]
     */
    public function getDescendantIds(array $ids, bool $includeSelf = true): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'WITH RECURSIVE tree(id) AS (
                SELECT id FROM attribute_entity WHERE id IN (:ids)
                UNION
                SELECT e.id FROM attribute_entity e INNER JOIN tree t ON e.parent_id = t.id
            )
            SELECT id FROM tree',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        return $includeSelf ? $rows : array_values(array_diff($rows, $ids));
    }

    /**
     * Ancestor IDs of each given entity, from its parent up to the root.
     *
     * @param string[] $ids
     *
     * @return array<string, string[]>
     */
    public function getAncestorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'WITH RECURSIVE up(start_id, ancestor_id, level) AS (
                SELECT id, parent_id, 1 FROM attribute_entity WHERE id IN (:ids) AND parent_id IS NOT NULL
                UNION ALL
                SELECT up.start_id, e.parent_id, up.level + 1 FROM attribute_entity e INNER JOIN up ON e.id = up.ancestor_id
                WHERE e.parent_id IS NOT NULL AND up.level < 100
            )
            SELECT start_id, ancestor_id FROM up ORDER BY start_id, level',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $ancestors = array_fill_keys($ids, []);
        foreach ($rows as $row) {
            $ancestors[$row['start_id']][] = $row['ancestor_id'];
        }

        return $ancestors;
    }

    /**
     * Sets the depth of the entity and shifts the depth of its whole subtree accordingly
     * (null unclassifies the subtree).
     */
    public function updateSubtreeDepth(string $id, ?int $depth): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'WITH RECURSIVE tree(id, depth) AS (
                SELECT id, CAST(:depth AS INT) FROM attribute_entity WHERE id = :id
                UNION
                SELECT e.id, t.depth + 1 FROM attribute_entity e INNER JOIN tree t ON e.parent_id = t.id
            )
            UPDATE attribute_entity AS a SET hierarchy_depth = tree.depth FROM tree WHERE a.id = tree.id',
            ['id' => $id, 'depth' => $depth],
            ['depth' => null === $depth ? ParameterType::NULL : ParameterType::INTEGER],
        );
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, int>
     */
    public function countChildren(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT parent_id, COUNT(*) FROM attribute_entity WHERE parent_id IN (:ids) GROUP BY parent_id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_map(intval(...), $rows);
    }

    /**
     * Gives the siblings consecutive positions, the moved entity placed at $position.
     */
    public function reorderSiblings(AttributeEntity $entity, int $position): void
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.list = :list')
            ->andWhere('e.id != :id')
            ->setParameter('list', $entity->getList()->getId())
            ->setParameter('id', $entity->getId())
            ->addOrderBy('e.position', 'ASC')
            ->addOrderBy('e.value', 'ASC');
        if (null !== $entity->getParent()) {
            $qb->andWhere('e.parent = :parent')->setParameter('parent', $entity->getParent()->getId());
        } else {
            $qb->andWhere('e.parent IS NULL');
        }

        /** @var AttributeEntity[] $siblings */
        $siblings = $qb->getQuery()->getResult();
        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$entity]);
        foreach ($siblings as $i => $sibling) {
            if ($sibling->getPosition() !== $i) {
                $sibling->setPosition($i);
            }
        }
    }

    /**
     * @return AttributeEntity[]
     */
    public function findHierarchyEntities(string $listId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.list = :list')
            ->andWhere('e.hierarchyDepth IS NOT NULL')
            ->setParameter('list', $listId)
            ->addOrderBy('e.hierarchyDepth', 'ASC')
            ->addOrderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
