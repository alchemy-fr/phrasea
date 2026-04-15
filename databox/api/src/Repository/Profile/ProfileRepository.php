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

    public function removeFromProfile(string $profileId, array $itemIds): void
    {
        $this->_em->createQueryBuilder()
            ->delete()
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :pid')
            ->andWhere('t.id IN (:ids)')
            ->setParameters([
                'pid' => $profileId,
                'ids' => $itemIds,
            ])
            ->getQuery()
            ->execute();
    }

    public function getMaxPosition(string $profileId): int
    {
        return $this->_em->createQueryBuilder()
            ->select('MAX(t.position) as m')
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :l')
            ->setParameter('l', $profileId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function hasDefinition(string $profileId, string $definitionId): bool
    {
        return null !== $this->_em->createQueryBuilder()
            ->select('1')
            ->setMaxResults(1)
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :l')
            ->andWhere('t.definition = :d')
            ->setParameter('l', $profileId)
            ->setParameter('d', $definitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getItemsIterator(string $profileId): iterable
    {
        return $this->_em->createQueryBuilder()
            ->select('t')
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :l')
            ->setParameter('l', $profileId)
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    public function getItem(string $profileId, string $itemId): ?ProfileItem
    {
        return $this->_em->getRepository(ProfileItem::class)
            ->findOneBy([
                'id' => $itemId,
                'profile' => $profileId,
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
