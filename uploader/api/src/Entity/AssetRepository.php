<?php

declare(strict_types=1);

namespace App\Entity;

use DateInterval;
use DateTime;
use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    public function attachCommit(array $files, string $commitId): void
    {
        $this
            ->createQueryBuilder('a')
            ->update(Asset::class, 'a')
            ->set('a.commit', ':commit')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('commit', $commitId)
            ->setParameter('ids', $files)
            ->getQuery()
            ->execute();
    }

    /**
     * @return Asset[]
     */
    public function findExpiredAssets(int $maxDaysRetention): iterable
    {
        $expirationDate = new DateTime();
        $expirationDate->sub(new DateInterval('P'.$maxDaysRetention.'D'));

        return $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.createdAt < :expiration_date')
            ->setParameter('expiration_date', $expirationDate)
            ->getQuery()
            ->getResult();
    }

    public function getAssetsTotalSize(array $ids): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('SUM(a.size) as total')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUnacknowledgedAssetsCount(string $commitId): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id) as total')
            ->andWhere('a.commit = :commit')
            ->andWhere('a.acknowledged = false')
            ->setParameter('commit', $commitId)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
