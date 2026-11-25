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
        $this->getEntityManager()->createQueryBuilder()
            ->delete()
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :pid')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('pid', $profileId)
            ->setParameter('ids', $itemIds)
            ->getQuery()
            ->execute();
    }

    public function getMaxPosition(string $profileId): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(t.position) as m')
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :l')
            ->setParameter('l', $profileId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function hasDefinition(string $profileId, string $definitionId, ?int $section = null): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->setMaxResults(1)
            ->from(ProfileItem::class, 't')
            ->andWhere('t.profile = :l')
            ->andWhere('t.definition = :d')
            ->setParameter('l', $profileId)
            ->setParameter('d', $definitionId);

        if (null !== $section) {
            $qb->andWhere('t.section = :s')
                ->setParameter('s', $section);
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    public function getItemsIterator(string $profileId): iterable
    {
        return $this->getEntityManager()->createQueryBuilder()
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
        return $this->getEntityManager()->getRepository(ProfileItem::class)
            ->findOneBy([
                'id' => $itemId,
                'profile' => $profileId,
            ]);
    }

    /**
     * Among the default profiles the user reads, the one applied while none is
     * chosen: a profile shared with the user (owned or by ACL) wins over a public
     * one, then the oldest.
     *
     * @param string[] $groupIds
     */
    public function findUserDefault(string $userId, array $groupIds): ?Profile
    {
        return $this->createQueryBuilderAcl($userId, $groupIds)
            ->andWhere('t.defaultProfile = true')
            ->addOrderBy('t.public', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
