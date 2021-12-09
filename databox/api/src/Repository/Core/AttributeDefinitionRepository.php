<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityRepository;

class AttributeDefinitionRepository extends EntityRepository
{
    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?array $workspaceIds = null, ?string $userId, array $groupIds): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->andWhere('t.searchable = true')
        ;

        if (null !== $userId) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                'attribute_definition',
                't',
                PermissionInterface::VIEW,
                false
            );
            $queryBuilder->andWhere('t.public = true OR ace.id IS NOT NULL');
        } else {
            $queryBuilder->andWhere('t.public = true');
        }

        if (null !== $workspaceIds) {
            $queryBuilder
                ->andWhere('t.workspace IN (:w)')
                ->setParameter('w', $workspaceIds)
            ;
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
