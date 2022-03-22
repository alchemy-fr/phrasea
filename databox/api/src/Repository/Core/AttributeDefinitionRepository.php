<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityRepository;

class AttributeDefinitionRepository extends EntityRepository
{
    const OPT_TYPES = 'types';
    const OPT_FACET_ENABLED = 'facet_enabled';

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?array $workspaceIds = null, ?string $userId, array $groupIds, array $options = []): array
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

        if ($options[self::OPT_TYPES] ?? null) {
            $queryBuilder
                ->andWhere('t.fieldType IN (:types)')
                ->setParameter('types', $options[self::OPT_TYPES]);
        }

        if ($options[self::OPT_FACET_ENABLED] ?? null) {
            $queryBuilder
                ->andWhere('t.facetEnabled = :fc')
                ->setParameter('fc', $options[self::OPT_FACET_ENABLED]);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByKey(string $key, string $workspaceId): ?AttributeDefinition
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.key = :key')
            ->andWhere('t.workspace = :ws')
            ->setParameter('key', $key)
            ->setParameter('ws', $workspaceId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
