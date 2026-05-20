<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\TagFilterRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class TagFilterRuleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, TagFilterRule::class);
    }

    /**
     * @return TagFilterRule[]
     */
    public function getRules(?string $userId, array $groupIds, ?string $workspaceId): array
    {
        $queryBuilder = $this
            ->createBaseQueryBuilder();

        $userWhere = [
            $userId ? 'a.userType = :ut AND a.userId = :uid OR a.userId IS NULL' : 'a.userId IS NULL',
        ];

        if (!empty($groupIds)) {
            $userWhere[] = 'a.userType = :gt AND a.userId IN (:gids)';
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

        if (null !== $workspaceId) {
            $queryBuilder
                ->andWhere('a.workspace = :wid')
                ->setParameter('wid', $workspaceId);
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
            'userId',
            'workspace',
        ] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \InvalidArgumentException(sprintf('Missing "%s" key', $key));
            }
        }

        foreach ([
            'userType' => 'ut',
            'userId' => 'uid',
            'workspace' => 'wid',
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
