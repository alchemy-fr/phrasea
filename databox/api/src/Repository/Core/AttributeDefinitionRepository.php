<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AttributeDefinitionRepository extends ServiceEntityRepository
{
    use SecurityAwareTrait;
    public const string OPT_TYPES = 'types';
    public const string OPT_SKIP_PERMS = 'skip_perms';
    public const string OPT_FACET_ENABLED = 'facet_enabled';
    public const string OPT_SUGGEST_ENABLED = 'suggest_enabled';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinition::class);
    }

    public function createQueryBuilderAcl(?string $userId, array $groupIds, bool $withConditions = true): QueryBuilder
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->innerJoin('t.class', 'c')
            ->innerJoin('t.workspace', 'w');

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
                'w_ace',
                paramPrefix: 'w',
            );
            $queryBuilder->setParameter('uid', $userId);
            if ($withConditions) {
                $queryBuilder->andWhere('c.public = true OR ac_ace.id IS NOT NULL');
                $queryBuilder->andWhere('w.public = true OR w.ownerId = :uid OR w_ace.id IS NOT NULL');
            }
        } else {
            if ($withConditions) {
                $queryBuilder->andWhere('c.public = true');
                $queryBuilder->andWhere('w.public = true');
            }
        }

        return $queryBuilder;
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributesWithPermission(?string $userId, array $groupIds): iterable
    {
        $queryBuilder = $this
            ->createQueryBuilderAcl($userId, $groupIds, withConditions: false)
            ->select('t.fieldType')
            ->addSelect('t.slug')
            ->addSelect('t.multiple')
            ->addSelect('t.searchBoost')
            ->addSelect('t.translatable')
            ->addSelect('w.public AS wPublic')
            ->addSelect('c.public AS cPublic')
            ->addSelect('w.id AS workspaceId')
            ->addSelect('w.enabledLocales AS enabledLocales')
            ->andWhere('t.searchable = true')
        ;

        if (null !== $userId) {
            $queryBuilder->addSelect('w.ownerId AS w_ownerId');
            $queryBuilder->addSelect('w_ace.id AS w_aceId');
            $queryBuilder->addSelect('ac_ace.id AS ac_aceId');
        }

        foreach ($queryBuilder
                     ->getQuery()
                     ->toIterable() as $row) {
            if (null !== $userId) {
                $row['allowed'] = ($row['wPublic'] || $row['w_ownerId'] === $userId || $row['w_aceId'])
                    && ($row['cPublic'] || $row['ac_aceId']);
                unset($row['w_ownerId'], $row['w_aceId'], $row['ac_aceId']);
            } else {
                $row['allowed'] = $row['wPublic'] && $row['cPublic'];
            }

            unset($row['wPublic'], $row['cPublic']);

            yield $row;
        }
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?string $userId, array $groupIds, array $options = []): array
    {
        $queryBuilder = $this
            ->createQueryBuilderAcl($userId, $groupIds)
            ->andWhere('t.searchable = true')
        ;

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

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceFallbackDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.fallback IS NOT NULL')
            ->andWhere('d.workspace = :workspace')
            ->andWhere('d.enabled = true')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AttributeDefinition[]
     */
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

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceDefinitionOfEntity(string $workspaceId, string $entityType): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.workspace = :workspace')
            ->andWhere('d.fieldType = :t')
            ->andWhere('d.entityType = :etype')
            ->setParameter('workspace', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('etype', $entityType)
            ->getQuery()
            ->getResult();
    }
}
