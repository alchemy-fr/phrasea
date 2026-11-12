<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\QueryPaginationTrait;
use App\Api\Traits\SecretScopeTrait;

/**
 * The secrets or variables of a workspace ("workspace", to its editors) or of
 * the instance ("global", to the administrators), by name.
 */
abstract class AbstractWorkspaceEnvCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use QueryPaginationTrait;
    use SecretScopeTrait;

    /**
     * @return class-string
     */
    abstract protected function getEntityClass(): string;

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $queryBuilder = $this->em->getRepository($this->getEntityClass())
            ->createQueryBuilder('t')
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if (null !== $workspace = $this->getSecretScopeWorkspace($operation)) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspace->getId());
        } else {
            $queryBuilder->andWhere('t.workspace IS NULL');
        }

        return self::paginateQuery($operation, $queryBuilder);
    }
}
