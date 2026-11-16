<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AssetPolicyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AssetPolicy::class);
    }

    /**
     * The enabled policies targeting the user or one of their groups, in one workspace or in all of them.
     *
     * @return AssetPolicy[]
     */
    public function getAssetPolicies(?string $workspaceId, ?string $userId, array $groups): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->innerJoin('t.users', 'u')
            ->andWhere('(u.userId = :userId AND u.userType = :userType) OR (u.userId IN (:groupIds) AND u.userType = :groupType)')
            ->andWhere('t.enabled = true')
            ->setParameter('userId', $userId)
            ->setParameter('groupIds', $groups)
            ->setParameter('userType', AssetPolicyUser::TYPE_USER)
            ->setParameter('groupType', AssetPolicyUser::TYPE_GROUP);

        if (null !== $workspaceId) {
            $queryBuilder
                ->andWhere('t.workspace = :wid')
                ->setParameter('wid', $workspaceId);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
