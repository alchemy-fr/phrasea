<?php

declare(strict_types=1);

namespace App\File;

use App\Border\FileAnalyzer\Analyzer\ChecksumAnalyzer;
use App\Border\FileAnalyzer\Analyzer\DocUniqueIdAnalyzer;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Integration\Core\FileAnalyzer\FileAnalyzerAssetActionEnum;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\FileDuplicateRepository;
use App\Repository\Core\FileRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps file analyses consistent with the file_duplicate table when duplicate
 * files appear or disappear (deletion, trash, restore, source replacement).
 */
final readonly class FileAnalysisReevaluator
{
    private const string DUPLICATE_MESSAGE_PREFIX = 'duplicate_';

    public function __construct(
        private EntityManagerInterface $em,
        private FileRepository $fileRepository,
        private FileDuplicateRepository $fileDuplicateRepository,
        private AssetRepository $assetRepository,
    ) {
    }

    /**
     * Prunes duplicate links pointing to files that are no longer the source of a
     * live asset, rewrites the analysis payload accordingly, and de-quarantines
     * assets whose analysis has no error left.
     *
     * @param string[] $fileIds
     */
    public function reevaluateFiles(array $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $file = $this->em->find(File::class, $fileId);
            if (!$file instanceof File) {
                continue;
            }

            $remainingByAnalyzer = [];
            foreach ($this->fileDuplicateRepository->findByFileId($file->getId()) as $link) {
                if (!$this->fileRepository->isActiveSourceFile($link->getDuplicateFile()->getId())) {
                    $this->em->remove($link);
                    continue;
                }
                $remainingByAnalyzer[$link->getAnalyzer()] = ($remainingByAnalyzer[$link->getAnalyzer()] ?? 0) + 1;
            }

            $this->rewriteAnalysis($file, $remainingByAnalyzer);
        }

        $this->em->flush();
    }

    private function rewriteAnalysis(File $file, array $remainingByAnalyzer): void
    {
        $analysis = $file->getAnalysis();
        if (empty($analysis['results'])
            || !in_array($analysis['status'] ?? null, [File::ANALYSIS_SUCCESS, File::ANALYSIS_FAILED], true)
        ) {
            return;
        }

        $hasError = false;
        foreach ($analysis['results'] as $i => $result) {
            $remaining = $remainingByAnalyzer[$result['name'] ?? ''] ?? 0;

            $messages = [];
            foreach ($result['output']['messages'] ?? [] as $message) {
                [$level, $type] = [$message[0] ?? 0, $message[1] ?? ''];
                if (str_starts_with((string) $type, self::DUPLICATE_MESSAGE_PREFIX)) {
                    if (0 === $remaining) {
                        continue;
                    }
                    $message[2] = array_merge($message[2] ?? [], ['count' => $remaining]);
                }
                if ($level >= LogLevelEnum::Error->value) {
                    $hasError = true;
                }
                $messages[] = $message;
            }

            if (empty($messages)) {
                unset($analysis['results'][$i]['output']['messages']);
            } else {
                $analysis['results'][$i]['output']['messages'] = $messages;
            }
        }
        $analysis['results'] = array_values($analysis['results']);

        $newStatus = $hasError ? File::ANALYSIS_FAILED : File::ANALYSIS_SUCCESS;
        $becameAccepted = File::ANALYSIS_FAILED === $analysis['status'] && File::ANALYSIS_SUCCESS === $newStatus;
        $analysis['status'] = $newStatus;

        $file->setAnalysis($analysis);
        $this->em->persist($file);

        if ($becameAccepted) {
            foreach ($this->assetRepository->findBySourceFileIds([$file->getId()]) as $asset) {
                if (AssetStatusEnum::Quarantined === $asset->getStatus() && !$asset->isDeleted()) {
                    $asset->setStatus(AssetStatusEnum::Accepted);
                    $this->em->persist($asset);
                }
            }
        }
    }

    /**
     * Symmetric operation of reevaluateFiles for files becoming active asset
     * sources again (asset restored from trash): re-creates the duplicate links
     * on matching analyzed files and re-fails their analysis.
     *
     * @param string[] $fileIds
     */
    public function restoreDuplicateLinks(array $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $file = $this->em->find(File::class, $fileId);
            if (!$file instanceof File || !$this->fileRepository->isActiveSourceFile($file->getId())) {
                continue;
            }

            $candidatesByAnalyzer = [
                ChecksumAnalyzer::getName() => $this->fileRepository->findAnalyzedFilesMatchingChecksum($file),
                DocUniqueIdAnalyzer::getName() => $this->fileRepository->findAnalyzedFilesMatchingDocUniqueId($file),
            ];

            foreach ($candidatesByAnalyzer as $analyzer => $candidates) {
                foreach ($candidates as $owner) {
                    $this->restoreLink($owner, $file, $analyzer);
                }
            }
        }

        $this->em->flush();
    }

    private function restoreLink(File $owner, File $duplicateFile, string $analyzer): void
    {
        $analysis = $owner->getAnalysis();
        if (!in_array($analysis['status'] ?? null, [File::ANALYSIS_SUCCESS, File::ANALYSIS_FAILED], true)) {
            return;
        }

        $resultIndex = null;
        foreach ($analysis['results'] ?? [] as $i => $result) {
            if (($result['name'] ?? null) === $analyzer) {
                $resultIndex = $i;
                break;
            }
        }
        if (null === $resultIndex) {
            return;
        }

        if (null !== $this->fileDuplicateRepository->findOneBy([
            'file' => $owner->getId(),
            'duplicateFile' => $duplicateFile->getId(),
            'analyzer' => $analyzer,
        ])) {
            return;
        }

        $link = new FileDuplicate();
        $link->setFile($owner);
        $link->setDuplicateFile($duplicateFile);
        $link->setAnalyzer($analyzer);
        $this->em->persist($link);

        $count = 1 + $this->fileDuplicateRepository->count([
            'file' => $owner->getId(),
            'analyzer' => $analyzer,
        ]);

        $messageType = self::DUPLICATE_MESSAGE_PREFIX.$analyzer;
        $messages = $analysis['results'][$resultIndex]['output']['messages'] ?? [];
        $found = false;
        foreach ($messages as $i => $message) {
            if (($message[1] ?? null) === $messageType) {
                $messages[$i][2] = array_merge($message[2] ?? [], ['count' => $count]);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $messages[] = [LogLevelEnum::Critical->value, $messageType, ['count' => $count]];
        }
        $analysis['results'][$resultIndex]['output']['messages'] = $messages;
        $analysis['status'] = File::ANALYSIS_FAILED;

        $owner->setAnalysis($analysis);
        $this->em->persist($owner);

        if (in_array(FileAnalyzerAssetActionEnum::QUARANTINE->value, $analysis['results'][$resultIndex]['actions'] ?? [], true)) {
            foreach ($this->assetRepository->findBySourceFileIds([$owner->getId()]) as $asset) {
                if (AssetStatusEnum::Accepted === $asset->getStatus() && !$asset->isDeleted()) {
                    $asset->setStatus(AssetStatusEnum::Quarantined);
                    $this->em->persist($asset);
                }
            }
        }
    }
}
