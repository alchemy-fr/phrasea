<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AttributeDefinition;

class AttributeDefinitionCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $filters = $context['filters'] ?? [];

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $queryBuilder = $this->em->getRepository(AttributeDefinition::class)
            ->createQueryBuilderAcl($userId, $groupIds)
        ;

        if ($filters['workspaceId'] ?? false) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        if ($filters['searchable'] ?? false) {
            $queryBuilder->andWhere('t.searchable = true');
        }

        return $queryBuilder
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
