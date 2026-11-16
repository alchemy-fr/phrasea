<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AttributeEntity;
use App\Security\AttributeEntityVisibilityResolver;
use Doctrine\ORM\QueryBuilder;

class AttributeEntityExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AttributeEntityVisibilityResolver $visibilityResolver,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (AttributeEntity::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $visibility = $this->visibilityResolver->resolve($operation);

        if (null !== $visibility->listIds) {
            // Restricted on the checked IDs themselves: the "list" filter skips the values with a non-integer key (`?list[x]=`)
            $parameterName = $queryNameGenerator->generateParameterName('editableLists');
            $queryBuilder
                ->andWhere(sprintf('%s.list IN (:%s)', $rootAlias, $parameterName))
                ->setParameter($parameterName, $visibility->listIds);

            return;
        }

        $visible = sprintf('%1$s.status = :approved', $rootAlias);
        $queryBuilder->setParameter('approved', AttributeEntity::STATUS_APPROVED);
        if (null !== $visibility->userId) {
            $visible .= sprintf(' OR %1$s.creatorId = :uid', $rootAlias);
            $queryBuilder->setParameter('uid', $visibility->userId);
        }
        if (!empty($visibility->pendingWorkspaceIds)) {
            $visible .= sprintf(' OR (%1$s.status = :pending AND %1$s.workspace IN (:editableWorkspaces))', $rootAlias);
            $queryBuilder
                ->setParameter('pending', AttributeEntity::STATUS_PENDING)
                ->setParameter('editableWorkspaces', $visibility->pendingWorkspaceIds);
        }
        $queryBuilder->andWhere($visible);
    }
}
