<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class WorkspaceRepository extends EntityRepository
{
    private array $workspacesCache = [];

    public function getUserWorkspaces(?string $userId, array $groupIds, ?array $ids = null): array
    {
        $k = $userId ?? 'anon.';
        if (isset($this->workspacesCache[$k])) {
            return $this->workspacesCache[$k];
        }

        if (null !== $userId) {
            $workspaces = $this->getAllowedWorkspaces($userId, $groupIds, $ids);
        } else {
            // TODO fix this point
            if (null !== $ids) {
                $workspaces = $this->createQueryBuilder('w')
                    ->andWhere('w.id IN (:wIds)')
                    ->setParameter('wIds', $ids)
                    ->getQuery()
                    ->getResult()
                ;
            } else {
                $workspaces = $this->findAll();
            }
        }

        return $this->workspacesCache[$k] = $workspaces;
    }

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
    public function getAllowedWorkspaces(?string $userId, ?array $groupIds, ?array $ids = []): array
    {
        return $this->createAllowedWorkspacesQueryBuilder($userId, $groupIds, $ids)
            ->getQuery()
            ->getResult();
    }

    private function createAllowedWorkspacesQueryBuilder(?string $userId, ?array $groupIds = null, ?array $ids = []): QueryBuilder
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
            PermissionInterface::VIEW,
            false
        );
        $queryBuilder->andWhere('w.public = true OR ace.id IS NOT NULL');

        return $queryBuilder;
    }
}
