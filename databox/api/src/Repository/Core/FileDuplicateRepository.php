<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\FileDuplicate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FileDuplicateRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, FileDuplicate::class);
    }

    /**
     * @return FileDuplicate[]
     */
    public function findByFileId(string $fileId): array
    {
        return $this->findBy(['file' => $fileId], ['createdAt' => 'ASC']);
    }

    /**
     * IDs of the files whose analysis references one of the given files as a duplicate.
     *
     * @return string[]
     */
    public function findOwnerFileIdsByDuplicateFileIds(array $fileIds): array
    {
        if (empty($fileIds)) {
            return [];
        }

        return array_map('strval', array_column($this->createQueryBuilder('fd')
            ->select('DISTINCT IDENTITY(fd.file) AS fileId')
            ->andWhere('fd.duplicateFile IN (:ids)')
            ->setParameter('ids', $fileIds)
            ->getQuery()
            ->getScalarResult(), 'fileId'));
    }
}
