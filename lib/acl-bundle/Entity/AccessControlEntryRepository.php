<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Doctrine\ORM\EntityRepository;

class AccessControlEntryRepository extends EntityRepository
{
    public function findAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('a')
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
}
