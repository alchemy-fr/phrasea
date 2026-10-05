<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer;

use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Repository\Core\FileDuplicateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maintains the file_duplicate rows of an analyzed file so they mirror
 * the last analysis results. Never flushes; callers flush.
 */
final readonly class FileDuplicateManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private FileDuplicateRepository $fileDuplicateRepository,
    ) {
    }

    /**
     * Replaces all duplicate links of the file with the given map.
     *
     * @param array<string, string[]> $duplicatesByAnalyzer analyzer name => duplicate file IDs
     */
    public function replaceDuplicates(File $file, array $duplicatesByAnalyzer): void
    {
        // Guard against IDs not referencing actual files (e.g. the debug analyzer).
        $duplicatesByAnalyzer = $this->filterExistingFileIds($duplicatesByAnalyzer);

        $existing = $this->getCurrentLinks($file);

        foreach ($existing as $link) {
            $expected = $duplicatesByAnalyzer[$link->getAnalyzer()] ?? [];
            if (!in_array($link->getDuplicateFile()->getId(), $expected, true)) {
                $this->em->remove($link);
            }
        }

        foreach ($duplicatesByAnalyzer as $analyzer => $duplicateFileIds) {
            $existingIds = array_map(
                fn (FileDuplicate $link): string => $link->getDuplicateFile()->getId(),
                array_filter($existing, fn (FileDuplicate $link): bool => $link->getAnalyzer() === $analyzer),
            );

            foreach (array_unique($duplicateFileIds) as $duplicateFileId) {
                if (!in_array($duplicateFileId, $existingIds, true)) {
                    $link = new FileDuplicate();
                    $link->setFile($file);
                    $link->setDuplicateFile($this->em->getReference(File::class, $duplicateFileId));
                    $link->setAnalyzer($analyzer);
                    $this->em->persist($link);
                }
            }
        }
    }

    public function removeAllForFile(File $file): void
    {
        foreach ($this->getCurrentLinks($file) as $link) {
            $this->em->remove($link);
        }
    }

    /**
     * @param array<string, string[]> $duplicatesByAnalyzer
     *
     * @return array<string, string[]>
     */
    private function filterExistingFileIds(array $duplicatesByAnalyzer): array
    {
        $allIds = array_unique(array_merge([], ...array_values($duplicatesByAnalyzer)));
        if (empty($allIds)) {
            return $duplicatesByAnalyzer;
        }

        $validIds = array_map('strval', array_column($this->em->createQueryBuilder()
            ->select('f.id')
            ->from(File::class, 'f')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('ids', $allIds)
            ->getQuery()
            ->getScalarResult(), 'id'));

        return array_map(
            fn (array $ids): array => array_values(array_intersect($ids, $validIds)),
            $duplicatesByAnalyzer,
        );
    }

    /**
     * Current links of the file, including ones persisted but not yet flushed.
     *
     * @return FileDuplicate[]
     */
    private function getCurrentLinks(File $file): array
    {
        $links = [];
        foreach ($this->fileDuplicateRepository->findBy(['file' => $file->getId()]) as $link) {
            $links[$link->getId()] = $link;
        }
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof FileDuplicate && $entity->getFile() === $file) {
                $links[$entity->getId()] = $entity;
            }
        }

        return array_values($links);
    }
}
