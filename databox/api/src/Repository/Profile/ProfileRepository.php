<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Profile::class);
    }

    public function removeFromList(string $listId, array $itemIds): void
    {
        $this->_em->createQueryBuilder()
            ->delete()
            ->from(Profile::class, 't')
            ->andWhere('t.list = :lid')
            ->andWhere('t.id IN (:ids)')
            ->setParameters([
                'lid' => $listId,
                'ids' => $itemIds,
            ])
            ->getQuery()
            ->execute();
    }

    public function getMaxPosition(string $listId): int
    {
        return $this->_em->createQueryBuilder()
            ->select('MAX(t.position) as m')
            ->from(ProfileItem::class, 't')
            ->andWhere('t.list = :l')
            ->setParameter('l', $listId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function hasDefinition(string $listId, string $definitionId): bool
    {
        return null !== $this->_em->createQueryBuilder()
            ->select('1')
            ->setMaxResults(1)
            ->from(ProfileItem::class, 't')
            ->andWhere('t.list = :l')
            ->andWhere('t.definition = :d')
            ->setParameter('l', $listId)
            ->setParameter('d', $definitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getItemsIterator(string $listId): iterable
    {
        return $this->_em->createQueryBuilder()
            ->select('t')
            ->from(ProfileItem::class, 't')
            ->andWhere('t.list = :l')
            ->setParameter('l', $listId)
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    public function getItem(string $listId, string $itemId): ?ProfileItem
    {
        return $this->_em->getRepository(ProfileItem::class)
            ->findOneBy([
                'id' => $itemId,
                'list' => $listId,
            ]);
    }

    public function createQueryBuilderAcl(?string $userId, array $groupIds): QueryBuilder
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
        ;

        if (null !== $userId) {
            $queryBuilder->addGroupBy('t.id');
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $userId,
                $groupIds,
                Profile::OBJECT_TYPE,
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
