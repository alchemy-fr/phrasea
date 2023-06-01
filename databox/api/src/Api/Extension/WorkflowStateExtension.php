<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Workflow\WorkflowState;
use App\Integration\IntegrationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class WorkflowStateExtension implements ContextAwareQueryCollectionExtensionInterface
{
    public function __construct(private readonly IntegrationRegistry $integrationRegistry, private readonly EntityManagerInterface $em)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (WorkflowState::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->addOrderBy($rootAlias.'.startedAt', 'DESC');
    }
}
