<?php

declare(strict_types=1);

namespace App\Repository\Page;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Page\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class PageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Page::class);
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
                Page::OBJECT_TYPE,
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
