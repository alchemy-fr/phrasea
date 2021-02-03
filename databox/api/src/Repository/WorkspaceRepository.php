<?php

declare(strict_types=1);

namespace App\Repository;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityRepository;

class WorkspaceRepository extends EntityRepository
{
    /**
     * @return string[]
     */
    public function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('w')
            ->select('w.id')
        ;

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            'workspace',
            'w',
            PermissionInterface::VIEW
        );

        return array_map(function (array $row): string {
            return $row['id'];
        }, $queryBuilder
            ->getQuery()
            ->getResult()
        );
    }
}
