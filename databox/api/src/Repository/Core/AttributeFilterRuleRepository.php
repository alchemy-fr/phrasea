<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\AttributeFilterRuleTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttributeFilterRuleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AttributeFilterRule::class);
    }

    /**
     * Returns the rules applying to the given user: rules targeting the user,
     * one of its groups, or nobody in particular (a rule without target applies to everyone).
     *
     * @return AttributeFilterRule[]
     */
    public function getRules(?string $userId, array $groupIds, ?string $workspaceId): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('a')
            ->distinct()
            ->leftJoin('a.targets', 't');

        $targetWhere = ['t.id IS NULL'];

        if ($userId) {
            $targetWhere[] = 't.userType = :ut AND t.userId = :uid';
            $queryBuilder
                ->setParameter('ut', AttributeFilterRuleTarget::TYPE_USER)
                ->setParameter('uid', $userId)
            ;
        }

        if (!empty($groupIds)) {
            $targetWhere[] = 't.userType = :gt AND t.userId IN (:gids)';
            $queryBuilder
                ->setParameter('gt', AttributeFilterRuleTarget::TYPE_GROUP)
                ->setParameter('gids', $groupIds)
            ;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$targetWhere));

        if (null !== $workspaceId) {
            $queryBuilder
                ->andWhere('a.workspace = :wid')
                ->setParameter('wid', $workspaceId);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
