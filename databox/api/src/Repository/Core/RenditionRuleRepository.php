<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\RenditionRule;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class RenditionRuleRepository extends EntityRepository
{
    /**
     * @return RenditionRule[]
     */
    public function getRules(?string $userId, array $groupIds, int $objectType, ?string $objectId): array
    {
        $queryBuilder = $this
            ->createBaseQueryBuilder()
            ->andWhere('a.objectType = :ot')
            ->setParameter('ot', $objectType);

        $userWhere = [
            $userId ? 'a.userType = :ut AND a.userId = :uid OR a.userId IS NULL' : 'a.userId IS NULL',
        ];

        if (!empty($groupIds)) {
            $userWhere[] = 'a.userType = :gt AND a.userId IN (:gids)';
            $queryBuilder
                ->setParameter('gt', RenditionRule::TYPE_GROUP)
                ->setParameter('gids', $groupIds)
            ;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$userWhere));

        if ($userId) {
            $queryBuilder
                ->setParameter('ut', RenditionRule::TYPE_USER)
                ->setParameter('uid', $userId)
            ;
        }

        if (null !== $objectId) {
            $queryBuilder
                ->andWhere('a.objectId = :oid')
                ->setParameter('oid', $objectId);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a');
    }
}
