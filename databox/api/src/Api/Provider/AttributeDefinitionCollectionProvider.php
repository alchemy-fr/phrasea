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

        $queryBuilder = $this->em->getRepository(AttributeDefinition::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.class', 'ac')
        ;

        if ($filters['workspaceId'] ?? false) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        if (!$this->isAdmin()) {
            $user = $this->security->getUser();

            if ($user instanceof JwtUser) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroups(),
                    'attribute_class',
                    'ac',
                    PermissionInterface::VIEW,
                    false
                );
                $queryBuilder->andWhere('ac.public = true OR ace.id IS NOT NULL');
            } else {
                $queryBuilder->andWhere('ac.public = true');
            }
        }

        return $queryBuilder
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
