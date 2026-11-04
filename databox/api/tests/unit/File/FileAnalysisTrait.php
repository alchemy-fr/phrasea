<?php

declare(strict_types=1);

namespace App\Tests\Unit\File;

use App\Entity\Core\File;

trait FileAnalysisTrait
{
    /**
     * Mirrors the legacy JSON payloads: `null` = never analyzed, `[]` = no
     * analysis needed, otherwise a full analysis result.
     */
    private function assignAnalysis(File $file, ?array $analysis): void
    {
        if (null === $analysis) {
            return;
        }

        if ([] === $analysis) {
            $file->setNoAnalysisNeeded();

            return;
        }

        $file->setAnalysisResult(
            $analysis['status'],
            $analysis['results'] ?? [],
            $analysis['hash'] ?? null,
            $analysis['message'] ?? null,
        );
    }
}
