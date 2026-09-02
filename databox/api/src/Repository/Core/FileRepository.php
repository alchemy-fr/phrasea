<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class FileRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, File::class);
    }

    /**
     * Only files being the source of a non-trashed asset qualify as duplicates.
     *
     * @return File[]
     */
    public function findDuplicatesByChecksum(File $file, int $limit = 1): array
    {
        return $this->createQueryBuilder('f')
            ->distinct()
            ->innerJoin(Asset::class, 'a', 'WITH', 'a.source = f.id')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('f.workspace = :ws')
            ->andWhere('f.checksum = :checksum')
            ->andWhere('f.id != :id')
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->setParameter('checksum', $file->getChecksum())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return File[]
     */
    public function findDuplicatesByDocUniqueId(File $file, int $limit = 1): array
    {
        $assetId = $this->_em->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->andWhere('a.source = :fileId')
            ->setParameter('fileId', $file->getId())
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        $queryBuilder = $this->createQueryBuilder('f')
            ->distinct()
            ->innerJoin(Asset::class, 'a', 'WITH', 'a.source = f.id')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('f.workspace = :ws')
            ->andWhere('f.docUniqueId = :duid')
            ->andWhere('f.id != :id')
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->setParameter('duid', $file->getDocUniqueId());

        if (null !== $assetId) {
            $queryBuilder
                ->leftJoin(AssetFileVersion::class, 'afv', 'WITH', 'f.id = afv.file')
                ->andWhere('afv.id IS NULL OR afv.asset != :assetId')
                ->setParameter('assetId', $assetId);
        }

        return $queryBuilder
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isActiveSourceFile(string $fileId): bool
    {
        return null !== $this->_em->createQueryBuilder()
            ->select('1')
            ->from(Asset::class, 'a')
            ->andWhere('a.source = :fileId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('fileId', $fileId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);
    }

    /**
     * Reverse match of findDuplicatesByChecksum: analyzed files for which
     * the given file would have been reported as a duplicate.
     *
     * @return File[]
     */
    public function findAnalyzedFilesMatchingChecksum(File $file): array
    {
        if (null === $file->getChecksum()) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.workspace = :ws')
            ->andWhere('f.checksum = :checksum')
            ->andWhere('f.id != :id')
            ->andWhere('f.analysis IS NOT NULL')
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->setParameter('checksum', $file->getChecksum())
            ->getQuery()
            ->getResult();
    }

    /**
     * Reverse match of findDuplicatesByDocUniqueId, excluding files whose
     * asset owns the given file as one of its versions.
     *
     * @return File[]
     */
    public function findAnalyzedFilesMatchingDocUniqueId(File $file): array
    {
        if (null === $file->getDocUniqueId()) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('f')
            ->andWhere('f.workspace = :ws')
            ->andWhere('f.docUniqueId = :duid')
            ->andWhere('f.id != :id')
            ->andWhere('f.analysis IS NOT NULL')
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->setParameter('duid', $file->getDocUniqueId());

        $versionAssetIds = $this->_em->createQueryBuilder()
            ->select('IDENTITY(afv.asset) AS assetId')
            ->from(AssetFileVersion::class, 'afv')
            ->andWhere('afv.file = :fileId')
            ->setParameter('fileId', $file->getId())
            ->getQuery()
            ->getScalarResult();
        $versionAssetIds = array_column($versionAssetIds, 'assetId');

        if (!empty($versionAssetIds)) {
            $queryBuilder
                ->leftJoin(Asset::class, 'a', 'WITH', 'a.source = f.id AND a.id IN (:versionAssetIds)')
                ->andWhere('a.id IS NULL')
                ->setParameter('versionAssetIds', $versionAssetIds);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * Stream the ids of every file of a workspace without hydrating entities.
     *
     * @return iterable<string>
     */
    public function iterateIdsByWorkspace(string $workspaceId): iterable
    {
        $result = $this->createQueryBuilder('f')
            ->select('f.id AS fileId')
            ->andWhere('f.workspace = :ws')
            ->setParameter('ws', $workspaceId)
            ->getQuery()
            ->toIterable([], AbstractQuery::HYDRATE_SCALAR);

        foreach ($result as $row) {
            yield $row['fileId'];
        }
    }
}
