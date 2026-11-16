<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\FileDuplicateRepository;

/**
 * Resolves the duplicate links of an analyzed file into the assets owning the
 * duplicate source files. Deleted assets and the assets the current user
 * may not see in a listing (voter and attribute filter rules, see
 * VisibleAssetResolver) are filtered out; the returned entities are serialized
 * through the standard asset normalization (like GET /assets/{id}).
 */
class DuplicateAssetResolver
{
    public function __construct(
        private readonly FileDuplicateRepository $fileDuplicateRepository,
        private readonly AssetRepository $assetRepository,
        private readonly VisibleAssetResolver $visibleAssetResolver,
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

        $candidates = array_filter(
            $this->assetRepository->findBySourceFileIds(array_keys($fileIdAnalyzers)),
            static fn (Asset $asset): bool => $asset->getId() !== $excludeAssetId && !$asset->isDeleted(),
        );

        $duplicates = [];
        foreach ($this->visibleAssetResolver->filterVisible(array_values($candidates)) as $duplicate) {
            $duplicates[] = [
                'asset' => $duplicate,
                'analyzers' => $fileIdAnalyzers[$duplicate->getSource()?->getId()] ?? [],
            ];
        }

        return $duplicates;
    }
}
