<?php

declare(strict_types=1);

namespace App\Repository\SavedSearch;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\SavedSearch\SavedSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class SavedSearchRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, SavedSearch::class);
    }

    public function createQueryBuilderAcl(?string $userId, array $groupIds): QueryBuilder
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
        ;

        if (null !== $userId) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                SavedSearch::OBJECT_TYPE,
                't',
                PermissionInterface::VIEW,
                false,
            );
            $queryBuilder->setParameter('uid', $userId);
            $queryBuilder->andWhere('t.public = true OR t.ownerId = :uid OR ace.id IS NOT NULL');
        } else {
            $queryBuilder->andWhere('t.public = true');
        }

        return $queryBuilder;
    }
}
