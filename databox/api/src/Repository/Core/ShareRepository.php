<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Share;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class ShareRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Share::class);
    }

    /**
     * @return Share[]
     */
    public function getSharesOfAssets(array $assetIds): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.assets', 'a')
            ->andWhere('a.id IN (:assetIds)')
            ->setParameter('assetIds', $assetIds)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getShareCount(array $assetIds): int
    {
        return (int) ($this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT a.id)')
            ->innerJoin('s.assets', 'a')
            ->andWhere('a.id IN (:assetIds)')
            ->setParameter('assetIds', $assetIds)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) ?? 0);
    }
}
