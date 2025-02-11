<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Security\Voter\AbstractVoter;

class WorkspaceIntegrationCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $filters = $context['filters'] ?? [];

        $queryBuilder = $this->em->getRepository(WorkspaceIntegration::class)
            ->createQueryBuilder('t')
        ;

        if ($filters['workspaceId'] ?? false) {
            $workspaceId = $filters['workspaceId'];
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);

            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);

            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspace->getId());
        } else {
            $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
            $queryBuilder
                ->andWhere('t.workspace IS NULL');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
