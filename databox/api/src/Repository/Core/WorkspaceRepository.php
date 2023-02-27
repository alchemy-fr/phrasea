<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class WorkspaceRepository extends EntityRepository
{
    /**
     * @return string[]
     */
    public function getPublicWorkspaceIds(): array
    {
        return array_map(function (array $row): string {
            return (string) $row['id'];
        }, $this
            ->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.public = true')
            ->getQuery()
            ->getResult()
        );
    }

    /**
     * @return string[]
     */
    public function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        return array_map(function (array $row): string {
            return (string) $row['id'];
        }, $this
            ->createAllowedWorkspacesQueryBuilder($userId, $groupIds)
            ->select('w.id')
            ->getQuery()
            ->getResult()
        );
    }

    private function createAllowedWorkspacesQueryBuilder(string $userId, ?array $groupIds = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('w');

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            'workspace',
            'w',
            PermissionInterface::VIEW
        );

        return $queryBuilder;
    }
}
