<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class AccessControlEntryRepository extends EntityRepository
{
    public static function joinAcl(
        QueryBuilder $queryBuilder,
        string $userId,
        array $groupIds,
        string $objectType,
        string $objectTableAlias,
        int $permission
    ): void
    {
        $hasGroups = !empty($groupIds);

        $queryBuilder
            ->innerJoin(
                AccessControlEntry::class,
                'ace',
                Join::WITH,
                sprintf(
                    'ace.objectType = :ot AND (ace.objectId = %s.id OR ace.objectId IS NULL) AND BIT_AND(ace.mask, :perm) = :perm'
                    .' AND (ace.userId IS NULL OR (ace.userType = :uty AND ace.userId = :uid)'
                    .($hasGroups ? ' OR (ace.userType = :gty AND ace.userId IN (:gids))' : '')
                    .')',
                    $objectTableAlias
                )
            )
            ->setParameter('uty', AccessControlEntry::TYPE_USER_VALUE)
            ->setParameter('ot', $objectType)
            ->setParameter('uid', $userId)
            ->setParameter('perm', $permission)
        ;

        if ($hasGroups) {
            $queryBuilder
                ->setParameter('gty', AccessControlEntry::TYPE_GROUP_VALUE)
                ->setParameter('gids', $groupIds);
        }
    }

    public function getAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array
    {
        $queryBuilder = $this
            ->createBaseQueryBuilder()
            ->andWhere('a.objectType = :ot')
            ->setParameter('ot', $objectType);

        $userWhere = [
            'a.userType = :ut AND a.userId = :uid OR a.userId IS NULL',
        ];

        if (!empty($groupIds)) {
            $userWhere[] = 'a.userType = :gt AND a.userId = (:gids)';
            $queryBuilder
                ->setParameter('gt', AccessControlEntry::TYPE_GROUP_VALUE)
                ->setParameter('gids', $groupIds)
            ;
        }

        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(...$userWhere))
            ->setParameter('ut', AccessControlEntry::TYPE_USER_VALUE)
            ->setParameter('uid', $userId)
        ;

        if (null !== $objectId) {
            $queryBuilder
                ->andWhere('a.objectId = :oid OR a.objectId IS NULL')
                ->setParameter('oid', $objectId);
        } else {
            $queryBuilder->andWhere('a.objectId IS NULL');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findAces(array $params = []): array
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        foreach ([
            'objectType' => 'ot',
            'userType' => 'ut',
            'objectId' => 'oid',
            'userId' => 'uid',
                 ] as $col => $alias) {
            if (isset($params[$col])) {
                $queryBuilder
                    ->andWhere(sprintf('a.%s = :%s', $col, $alias))
                    ->setParameter($alias, $params[$col]);
            }
        }
        foreach ([
            'objectId' => 'oid',
            'userId' => 'uid',
                 ] as $col => $alias) {
            if (array_key_exists($col, $params) && null === $params[$col]) {
                $queryBuilder->andWhere(sprintf('a.%s IS NULL', $col));
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getAllowedUserIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->getAllowedIds(AccessControlEntry::TYPE_USER_VALUE, $objectType, $objectId, $permission);
    }

    public function getAllowedGroupIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->getAllowedIds(AccessControlEntry::TYPE_GROUP_VALUE, $objectType, $objectId, $permission);
    }

    private function getAllowedIds(int $userType, string $objectType, string $objectId, int $permission): array
    {
        return array_map(function (array $row): ?string {
            return $row['userId'];
        }, $this
            ->createBaseQueryBuilder()
            ->select('DISTINCT a.userId')
            ->andWhere('a.objectType = :ot')
            ->andWhere('a.objectId = :oid OR a.objectId IS NULL')
            ->andWhere('BIT_AND(a.mask, :p) = :p')
            ->andWhere('a.userType = :ut')
            ->setParameter('ut', $userType)
            ->setParameter('ot', $objectType)
            ->setParameter('oid', $objectId)
            ->setParameter('p', $permission)
            ->getQuery()
            ->getScalarResult()
        );
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a');
    }
}
