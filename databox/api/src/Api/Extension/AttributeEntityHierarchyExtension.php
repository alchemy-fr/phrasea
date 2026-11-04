<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Attribute\Hierarchy\EntityHierarchyCriteriaResolver;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Hierarchy parameters of the entity listing on the ORM (AttributeEntitySearch applies them on Elasticsearch).
 */
final readonly class AttributeEntityHierarchyExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private EntityHierarchyCriteriaResolver $criteriaResolver,
        private AttributeEntityRepository $repository,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (AttributeEntity::class !== $resourceClass || null === $operation) {
            return;
        }

        $criteria = $this->criteriaResolver->resolve($operation);
        if ($criteria->isEmpty()) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        if ($criteria->none) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }
        if (null !== $criteria->listId) {
            $queryBuilder->andWhere(sprintf('%s.list = :h_list', $alias))->setParameter('h_list', $criteria->listId);
        }
        if (null !== $criteria->parentId) {
            $queryBuilder
                ->andWhere(sprintf('%s.parent = :h_parent', $alias))
                ->setParameter('h_parent', $criteria->parentId)
                ->addOrderBy(sprintf('%s.position', $alias), 'ASC');
        }
        if (null !== $criteria->depth) {
            $queryBuilder->andWhere(sprintf('%s.hierarchyDepth = :h_depth', $alias))->setParameter('h_depth', $criteria->depth);
        }
        if (null !== $criteria->minDepth) {
            $queryBuilder->andWhere(sprintf('%s.hierarchyDepth >= :h_min_depth', $alias))->setParameter('h_min_depth', $criteria->minDepth);
        }
        if (null !== $criteria->maxDepth) {
            $queryBuilder->andWhere(sprintf('%s.hierarchyDepth <= :h_max_depth', $alias))->setParameter('h_max_depth', $criteria->maxDepth);
        }
        if (null !== $criteria->branchId) {
            $descendantIds = $this->repository->getDescendantIds([$criteria->branchId], includeSelf: false);
            $queryBuilder->andWhere(sprintf('%s.id IN (:h_branch)', $alias))->setParameter('h_branch', $descendantIds ?: ['00000000-0000-0000-0000-000000000000']);
        }
        if (null !== $criteria->placeholder) {
            $queryBuilder->andWhere(sprintf('%s.placeholder = :h_placeholder', $alias))->setParameter('h_placeholder', $criteria->placeholder);
        }
    }
}
