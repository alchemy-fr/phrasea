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
     * @return AssetPolicy[]
     */
    public function getAssetPolicies(string $workspaceId, ?string $userId, array $groups): array
    {
        return $this
            ->createQueryBuilder('t')
            ->innerJoin('t.users', 'u')
            ->andWhere('(u.userId = :userId AND u.userType = :userType) OR (u.userId IN (:groupIds) AND u.userType = :groupType)')
            ->andWhere('t.workspace = :wid')
            ->andWhere('t.enabled = true')
            ->setParameters([
                'userId' => $userId,
                'groupIds' => $groups,
                'wid' => $workspaceId,
                'userType' => AssetPolicyUser::TYPE_USER,
                'groupType' => AssetPolicyUser::TYPE_GROUP,
            ])
            ->getQuery()
            ->getResult();
    }
}
