<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class RenditionDefinitionRepository extends ServiceEntityRepository
{
    use SecurityAwareTrait;

    private CacheInterface $cache;

    public function __construct(ManagerRegistry $registry, TemporaryCacheFactory $cacheFactory)
    {
        parent::__construct($registry, RenditionDefinition::class);
        $this->cache = $cacheFactory->createCache();
    }

    public function addAclConditions(
        QueryBuilder $queryBuilder,
        ?string $userId,
        array $groupIds,
        bool $withConditions = true,
    ): QueryBuilder {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->innerJoin($rootAlias.'.policy', 'acl_c')
            ->innerJoin($rootAlias.'.workspace', 'acl_w');

        if (null !== $userId) {
            $queryBuilder
                ->addGroupBy($rootAlias.'.id')
                ->addGroupBy('acl_c.id')
                ->addGroupBy('acl_w.id')
                ->addGroupBy('acl_c.id')
                ->addGroupBy('ap_ace.id')
                ->addGroupBy('w_ace.id');
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                AttributePolicy::OBJECT_TYPE,
                'acl_c',
                PermissionInterface::VIEW,
                false,
                'ap_ace'
            );
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                Workspace::OBJECT_TYPE,
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
}
