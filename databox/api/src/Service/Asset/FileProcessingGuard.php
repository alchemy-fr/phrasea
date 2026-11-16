<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Service\Asset\Exception\FileNotProcessableException;

/**
 * Decides whether the content of a file may be read by a processing (rendition
 * build, analysis, external service…). Refused when:
 * - the content check rejected the file (CheckFileContentAction);
 * - its analysis failed, or is still pending while the workspace requires it.
 *
 * A quarantine bypass (AssetVoter::QUARANTINE_BYPASS) accepts the source file
 * (File::bypassAnalysis()): it becomes processable again.
 */
readonly class FileProcessingGuard
{
    /**
     * @param bool $allowPendingAnalysis for the builds the workflow runs before the analysis on purpose (e.g. the base renditions)
     *
     * @throws FileNotProcessableException
     */
    public function assertProcessable(File $file, bool $allowPendingAnalysis = false): void
    {
        $refusal = $this->getRefusal($file, $allowPendingAnalysis);
        if (null !== $refusal) {
            throw new FileNotProcessableException($file, $refusal);
        }
    }

    /**
     * @return string|null why the file cannot be processed, null when it can
     */
    public function getRefusal(File $file, bool $allowPendingAnalysis = false): ?string
    {
        if ($file->isContentRejected()) {
            return 'its content was rejected';
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

        return null !== $source ? $this->getRefusal($source) : null;
    }
}
