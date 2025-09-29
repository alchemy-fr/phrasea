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
            ->andWhere('s.asset IN (:assetIds)')
            ->setParameter('assetIds', $assetIds)
            ->getQuery()
            ->getResult();
    }

    public function getShareCount(array $assetIds): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.asset)')
            ->andWhere('s.asset IN (:assetIds)')
            ->setParameter('assetIds', $assetIds)
            ->addGroupBy('s.asset')
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) ?? 0;
    }
}
