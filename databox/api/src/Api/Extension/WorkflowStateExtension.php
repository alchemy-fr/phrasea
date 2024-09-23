<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Workflow\WorkflowState;
use Doctrine\ORM\QueryBuilder;

class WorkflowStateExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (WorkflowState::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->addOrderBy($rootAlias.'.startedAt', 'DESC');
    }
}
