<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Core\TagFilterRule;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

class TagFilterRuleRepository extends EntityRepository
{
    /**
     * @return TagFilterRule[]
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
            $userWhere[] = 'a.userType = :gt AND a.userId = (:gids)';
            $queryBuilder
                ->setParameter('gt', TagFilterRule::TYPE_GROUP)
                ->setParameter('gids', $groupIds)
            ;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$userWhere));

        if ($userId) {
            $queryBuilder
                ->setParameter('ut', TagFilterRule::TYPE_USER)
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

    public function findRules(array $params = []): array
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        foreach ([
            'userType',
            'objectType',
            'objectId',
            'userId',
                 ] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new InvalidArgumentException(sprintf('Missing "%s" key', $key));
            }
        }

        foreach ([
            'userType' => 'ut',
            'userId' => 'uid',
            'objectType' => 'ot',
            'objectId' => 'oid',
                 ] as $col => $alias) {
            if (isset($params[$col])) {
                $queryBuilder
                    ->andWhere(sprintf('a.%s = :%s', $col, $alias))
                    ->setParameter($alias, $params[$col]);
            }
        }

        if (array_key_exists('userId', $params) && null === $params['userId']) {
            $queryBuilder->andWhere('a.uid IS NULL');
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
