<?php

declare(strict_types=1);

namespace App\Repository\Core;

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
     * @return File[]
     */
    public function findDuplicatesByChecksum(File $file, int $limit = 1): array
    {
        return $this->createQueryBuilder('f')
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

    public function findDuplicatesByDocUniqueId(File $file, int $limit = 1): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.workspace = :ws')
            ->andWhere('f.docUniqueId = :duid')
            ->andWhere('f.id != :id')
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->setParameter('duid', $file->getDocUniqueId())
            ->setMaxResults($limit)
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
