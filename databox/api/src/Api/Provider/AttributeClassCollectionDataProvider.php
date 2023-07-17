<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\SecurityAwareTrait;
use App\Entity\Core\AttributeClass;

class AttributeClassCollectionDataProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            return [];
        }

        $filters = $context['filters'] ?? [];

        $queryBuilder = $this->em->getRepository(AttributeClass::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.workspace', 'w');

        if ($filters['workspaceId'] ?? false) {
            $queryBuilder
                ->andWhere('w.id = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        if (!$this->isChuckNorris()) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroupIds(),
                'workspace',
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
