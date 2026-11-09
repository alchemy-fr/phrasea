<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\QueryPaginationTrait;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The secrets or variables of a workspace ("workspace", to its editors) or of
 * the instance ("global", to the administrators), by name.
 */
abstract class AbstractWorkspaceEnvCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use QueryPaginationTrait;

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

        if (null !== $workspaceId = self::getParameterId($operation, 'workspace')) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace, 'Cannot edit Workspace');

            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspace->getId());
        } elseif (self::getParameterValue($operation, 'global', false)) {
            if (!$this->isAdmin()) {
                throw new AccessDeniedHttpException('The instance ones are managed by the administrators');
            }

            $queryBuilder->andWhere('t.workspace IS NULL');
        } else {
            throw new BadRequestHttpException('You must provide "workspace" or "global"');
        }

        return self::paginateQuery($operation, $queryBuilder);
    }
}
