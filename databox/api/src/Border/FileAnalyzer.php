<?php

namespace App\Border;

use App\Entity\Core\File;

final readonly class FileAnalyzer
{
    public function __construct()
    {
    }

    public function analyzeFile(File $file, bool $force = false): void
    {
        if ($file->isAnalyzed() && !$force) {
            return;
        }

        if (!$this->requiresAnalysis($file)) {
            return;
        }

        // Simulate analysis process
        // In a real implementation, this would involve more complex logic
        // such as extracting metadata, generating thumbnails, etc.
        $analysis = [
            'integrity' => [
                'similar_files' => [],
                'duplicates' => [],
                'corrupted' => false,
            ],
        ];

        $file->setAnalysis($analysis);
    }

    public function requiresAnalysis(File $file): bool
    {
        if (true) {
            return true;
        }

        $file->setNoAnalysisNeeded();

        return false;
    }
}
