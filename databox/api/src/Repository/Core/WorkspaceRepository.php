<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Workspace;
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
            return (string) $row['id'];
        }, $queryBuilder
            ->getQuery()
            ->getResult()
        );
    }

    /**
     * @return Workspace[]
     */
    public function getAllowedWorkspaces(string $userId, array $groupIds, ?array $ids = []): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('w')
            ->select('w')
        ;

        if (null !== $ids) {
            $queryBuilder
                ->andWhere('w.id IN (:wIds)')
                ->setParameter('wIds', $ids)
            ;
        }

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            'workspace',
            'w',
            PermissionInterface::VIEW
        );

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}