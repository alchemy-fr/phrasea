<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\FileDuplicateRepository;
use App\Security\Voter\AbstractVoter;

/**
 * Resolves the duplicate links of an analyzed file into the assets owning the
 * duplicate source files. Assets the current user cannot view are filtered
 * out; the returned entities are serialized through the standard asset
 * normalization (like GET /assets/{id}).
 */
class DuplicateAssetResolver
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly FileDuplicateRepository $fileDuplicateRepository,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    /**
     * @return array<int, array{asset: Asset, analyzers: string[]}>
     */
    public function resolveDuplicates(File $file, ?string $excludeAssetId = null): array
    {
        $fileIdAnalyzers = [];
        foreach ($this->fileDuplicateRepository->findByFileId($file->getId()) as $link) {
            $fileIdAnalyzers[$link->getDuplicateFile()->getId()][] = $link->getAnalyzer();
        }

        $duplicates = [];
        foreach ($this->assetRepository->findBySourceFileIds(array_keys($fileIdAnalyzers)) as $duplicate) {
            if ($duplicate->getId() === $excludeAssetId
                || $duplicate->isDeleted()
                || !$this->isGranted(AbstractVoter::READ, $duplicate)
            ) {
                continue;
            }

            $duplicates[] = [
                'asset' => $duplicate,
                'analyzers' => $fileIdAnalyzers[$duplicate->getSource()?->getId()] ?? [],
            ];
        }

        return $duplicates;
    }
}
