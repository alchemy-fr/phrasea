<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class WorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Workspace::class);
    }

    /**
     * @return string[]
     */
    public function getPublicWorkspaceIds(): array
    {
        return array_map(fn (array $row): string => (string) $row['id'], $this
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
    public function getAllowedWorkspaceIds(?string $userId, array $groupIds): array
    {
        return array_map(fn (array $row): string => (string) $row['id'], $this
            ->createAllowedWorkspacesQueryBuilder($userId, $groupIds)
            ->select('DISTINCT w.id')
            ->getQuery()
            ->getResult()
        );
    }

    private function createAllowedWorkspacesQueryBuilder(?string $userId, ?array $groupIds = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('w');

        if (null === $userId) {
            return $queryBuilder
                ->andWhere('w.public = true');
        }

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            'workspace',
            'w',
            PermissionInterface::VIEW,
            false,
        );

        $queryBuilder->andWhere('w.public = true OR ace.id IS NOT NULL OR w.ownerId = :uid');

        return $queryBuilder;
    }
}
