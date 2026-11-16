<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\Exception\FileNotProcessableException;

/**
 * Decides whether the content of a file may be read by a processing (rendition
 * build, analysis, external service…). Refused when:
 * - the content check rejected the file (CheckFileContentAction);
 * - its asset is quarantined;
 * - its analysis failed, or is still pending while the workspace requires it.
 *
 * A quarantine bypass (AssetVoter::QUARANTINE_BYPASS) accepts the source file and
 * the asset: they become processable again.
 */
readonly class FileProcessingGuard
{
    public function __construct(
        private AssetRepository $assetRepository,
    ) {
    }

    /**
     * @param Asset|null $asset                the asset the file is processed for; when null, the assets having it as source
     * @param bool       $allowPendingAnalysis for the builds the workflow runs before the analysis on purpose (e.g. the base renditions)
     *
     * @throws FileNotProcessableException
     */
    public function assertProcessable(File $file, ?Asset $asset = null, bool $allowPendingAnalysis = false): void
    {
        $refusal = $this->getRefusal($file, $asset, $allowPendingAnalysis);
        if (null !== $refusal) {
            throw new FileNotProcessableException($file, $refusal);
        }
    }

    /**
     * @return string|null why the file cannot be processed, null when it can
     */
    public function getRefusal(File $file, ?Asset $asset = null, bool $allowPendingAnalysis = false): ?string
    {
        if ($file->isContentRejected()) {
            return 'its content was rejected';
        }

        $assets = null !== $asset ? [$asset] : $this->findSourceAssets($file);
        foreach ($assets as $a) {
            if (AssetStatusEnum::Quarantined === $a->getStatus()) {
                return sprintf('asset "%s" is quarantined', $a->getId());
            }
        }

        if (!$file->isAccepted()) {
            return 'its analysis failed';
        }

        if (!$allowPendingAnalysis
            && !$file->isAnalyzed()
            && true === $file->getWorkspace()?->isFileAnalysisRequired()) {
            return 'its analysis is pending';
        }

        return null;
    }

    /**
     * Whether the asset content (its source file) can be processed.
     *
     * @return string|null why the asset cannot be processed, null when it can
     */
    public function getAssetRefusal(Asset $asset): ?string
    {
        $source = $asset->getSource();
        if (null !== $source) {
            return $this->getRefusal($source, $asset);
        }

        return AssetStatusEnum::Quarantined === $asset->getStatus() ? sprintf('asset "%s" is quarantined', $asset->getId()) : null;
    }

    /**
     * @return Asset[]
     */
    private function findSourceAssets(File $file): array
    {
        if (null === $file->getId()) {
            return [];
        }

        return $this->assetRepository->findBySourceFileIds([$file->getId()]);
    }
}
