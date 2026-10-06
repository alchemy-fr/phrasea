<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Workspace;

class AttributePolicyCollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            return [];
        }

        $queryBuilder = $this->em->getRepository(AttributePolicy::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.workspace', 'w');

        if (null !== $workspaceId = self::getParameterValue($operation, 'workspaceId')) {
            $queryBuilder
                ->andWhere('w.id = :ws')
                ->setParameter('ws', basename((string) (\is_array($workspaceId) ? reset($workspaceId) : $workspaceId)));
        }

        if (!$this->isAdmin()) {
            $queryBuilder->addGroupBy('t.id');
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroups(),
                Workspace::OBJECT_TYPE,
                'w',
                PermissionInterface::EDIT,
                false
            );
            $queryBuilder->andWhere('ace.id IS NOT NULL OR w.ownerId = :uid');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
