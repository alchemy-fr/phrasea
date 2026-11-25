<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    /**
     * Only the files not committed yet.
     *
     * @return int the number of files attached
     */
    public function attachCommit(array $files, string $commitId): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->update(Asset::class, 'a')
            ->set('a.commit', ':commit')
            ->andWhere('a.id IN (:ids)')
            ->andWhere('a.commit IS NULL')
            ->setParameter('commit', $commitId)
            ->setParameter('ids', $files)
            ->getQuery()
            ->execute();
    }

    /**
     * Reads the database: the files attached in bulk by attachCommit() are missing from the collection of a commit
     * already loaded.
     *
     * @return string[]
     */
    public function getCommitAssetIds(string $commitId): array
    {
        $ids = $this
            ->createQueryBuilder('a')
            ->select('a.id')
            ->andWhere('a.commit = :commit')
            ->setParameter('commit', $commitId)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(static fn (mixed $id): string => (string) $id, $ids);
    }

    /**
     * Reads the database, whatever the entities already loaded hold.
     *
     * @param string[] $ids
     *
     * @return array<int, string|null> the commit of each file found, null when it is not committed
     */
    public function getCommitIdsOfAssets(array $ids): array
    {
        $commitIds = $this
            ->createQueryBuilder('a')
            ->select('IDENTITY(a.commit)')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(static fn (mixed $id): ?string => null === $id ? null : (string) $id, $commitIds);
    }

    /**
     * @return Asset[]
     */
    public function findExpiredAssets(int $maxDaysRetention): iterable
    {
        $expirationDate = (new \DateTimeImmutable())
            ->sub(new \DateInterval('P'.$maxDaysRetention.'D'));

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
