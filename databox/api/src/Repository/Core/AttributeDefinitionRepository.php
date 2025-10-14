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

    public function addAclConditions(QueryBuilder $queryBuilder, ?string $userId, array $groupIds, bool $withConditions = true): QueryBuilder
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->innerJoin($rootAlias.'.policy', 'acl_c')
            ->innerJoin($rootAlias.'.workspace', 'acl_w');

        if (null !== $userId) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                'attribute_policy',
                'acl_c',
                PermissionInterface::VIEW,
                false,
                'ap_ace'
            );
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                'workspace',
                'acl_w',
                PermissionInterface::VIEW,
                false,
                'w_ace',
                paramPrefix: 'w',
            );
            $queryBuilder->setParameter('uid', $userId);
            if ($withConditions) {
                $queryBuilder->andWhere('acl_c.public = true OR ap_ace.id IS NOT NULL');
                $queryBuilder->andWhere('acl_w.public = true OR acl_w.ownerId = :uid OR w_ace.id IS NOT NULL');
            }
        } else {
            if ($withConditions) {
                $queryBuilder->andWhere('acl_c.public = true');
                $queryBuilder->andWhere('acl_w.public = true');
            }
        }

        return $queryBuilder;
    }

    public function createQueryBuilderAcl(?string $userId, array $groupIds, bool $withConditions = true): QueryBuilder
    {
        return $this->addAclConditions($this->createQueryBuilder('t'), $userId, $groupIds, $withConditions);
    }

    public function getAttributeDefinitionBySlug(string $workspaceId, string $slug): ?AttributeDefinition
    {
        return $this->findOneBy([
            'slug' => $slug,
            'workspace' => $workspaceId,
        ]);
    }

    public function getAttributeDefinitions(string $workspaceId): iterable
    {
        return $this->findBy([
            'workspace' => $workspaceId,
        ]);
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
            ->addSelect('acl_w.public AS wPublic')
            ->addSelect('acl_c.public AS cPublic')
            ->addSelect('acl_w.id AS workspaceId')
            ->addSelect('acl_w.enabledLocales AS enabledLocales')
            ->andWhere('t.searchable = true')
        ;

        if (null !== $userId) {
            $queryBuilder->addSelect('acl_w.ownerId AS w_ownerId');
            $queryBuilder->addSelect('w_ace.id AS w_aceId');
            $queryBuilder->addSelect('ap_ace.id AS ap_aceId');
        }

        foreach ($queryBuilder
                     ->getQuery()
                     ->toIterable() as $row) {
            if (null !== $userId) {
                $row['allowed'] = ($row['wPublic'] || $row['w_ownerId'] === $userId || $row['w_aceId'])
                    && ($row['cPublic'] || $row['ap_aceId']);
                unset($row['w_ownerId'], $row['w_aceId'], $row['ap_aceId']);
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
    public function getWorkspaceDefinitionOfEntity(string $workspaceId, string $entityListId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.workspace = :workspace')
            ->andWhere('d.fieldType = :t')
            ->andWhere('d.entityList = :etype')
            ->setParameter('workspace', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('etype', $entityListId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AttributeDefinition[]
     */
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
