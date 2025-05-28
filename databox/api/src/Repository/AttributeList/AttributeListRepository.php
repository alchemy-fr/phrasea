<?php

declare(strict_types=1);

namespace App\Repository\AttributeList;

use App\Entity\AttributeList\AttributeList;
use App\Entity\AttributeList\AttributeListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttributeListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AttributeList::class);
    }

    public function removeFromList(string $listId, array $itemIds): void
    {
        $this->_em->createQueryBuilder()
            ->delete()
            ->from(AttributeListItem::class, 't')
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
            ->from(AttributeListItem::class, 't')
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
            ->from(AttributeListItem::class, 't')
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
            ->from(AttributeListItem::class, 't')
            ->andWhere('t.list = :l')
            ->setParameter('l', $listId)
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    public function getItem(string $listId, string $itemId): ?AttributeListItem
    {
        return $this->_em->getRepository(AttributeListItem::class)
            ->findOneBy([
                'id' => $itemId,
                'list' => $listId,
            ]);
    }
}
