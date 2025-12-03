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
            ->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.public = true')
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
            ->select('DISTINCT t.id')
            ->getQuery()
            ->getResult()
        );
    }

    private function createAllowedWorkspacesQueryBuilder(?string $userId, ?array $groupIds = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('t');

        if (null === $userId) {
            return $queryBuilder
                ->andWhere('t.public = true');
        }

        $queryBuilder->addGroupBy('t.id');
        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            Workspace::OBJECT_TYPE,
            't',
            PermissionInterface::VIEW,
            false,
        );

        $queryBuilder->andWhere('t.public = true OR ace.id IS NOT NULL OR t.ownerId = :uid');

        return $queryBuilder;
    }
}
