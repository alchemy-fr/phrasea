<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\RenditionDefinition;

class RenditionDefinitionCollectionProvider extends AbstractCollectionProvider
{
    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $queryBuilder = $this->em->getRepository(RenditionDefinition::class)
            ->createQueryBuilder('t');

        $filters = $context['filters'] ?? [];
        if (isset($filters['workspaceIds'])) {
            $queryBuilder->andWhere('t.workspace IN (:wids)')
                ->setParameter('wids', $filters['workspaceIds']);
        }
        if (isset($filters['workspaceId'])) {
            $queryBuilder->andWhere('t.workspace = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        return $queryBuilder
            ->addOrderBy('t.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
