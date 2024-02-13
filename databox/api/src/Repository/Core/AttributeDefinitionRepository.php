<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use App\Util\SecurityAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttributeDefinitionRepository extends ServiceEntityRepository implements AttributeDefinitionRepositoryInterface
{
    use SecurityAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinition::class);
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?string $userId, array $groupIds, array $options = []): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->andWhere('t.searchable = true')
            ->innerJoin('t.class', 'c')
            ->innerJoin('t.workspace', 'w')
        ;

        if (!$this->isSuperAdmin()) {
            if (null !== $userId) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $userId,
                    $groupIds,
                    'attribute_class',
                    'c',
                    PermissionInterface::VIEW,
                    false,
                    'ac_ace'
                );
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $userId,
                    $groupIds,
                    'workspace',
                    'w',
                    PermissionInterface::VIEW,
                    false,
                    'w_ace'
                );
                $queryBuilder->andWhere('c.public = true OR ac_ace.id IS NOT NULL');
                $queryBuilder->andWhere('w.public = true OR w.ownerId = :uid OR w_ace.id IS NOT NULL');
                $queryBuilder->setParameter('uid', $userId);
            } else {
                $queryBuilder->andWhere('c.public = true');
                $queryBuilder->andWhere('w.public = true');
            }
        }

        if ($options[self::OPT_TYPES] ?? null) {
            $queryBuilder
                ->andWhere('t.fieldType IN (:types)')
                ->setParameter('types', $options[self::OPT_TYPES]);
        }

        if ($options[self::OPT_SUGGEST_ENABLED] ?? null) {
            $queryBuilder
                ->andWhere('t.suggest = :suggest')
                ->setParameter('suggest', $options[self::OPT_SUGGEST_ENABLED]);
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

    public function getWorkspaceFallbackDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.fallback IS NOT NULL')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }

    public function getWorkspaceInitializeDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.initialValues IS NOT NULL')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }

    public function getWorkspaceDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }
}
