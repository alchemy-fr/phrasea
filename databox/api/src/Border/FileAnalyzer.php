<?php

namespace App\Border;

use App\Entity\Core\File;
use App\Entity\Core\Workspace;

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

        if (!$this->preAnalyzeFile($file)) {
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

    /**
     * @return bool Whether to proceed File analysis
     */
    public function preAnalyzeFile(File $file): bool
    {
        $settings = $this->getWorkspaceAnalyzerSettings($file->getWorkspace());
        $preAnalyzers = array_filter($settings, fn (array $setting): bool => $setting['preAnalyzer'] ?? false);

        if (!empty($preAnalyzers)) {
            $analysis = [];
            foreach ($preAnalyzers as $preAnalyzer) {
                if ($preAnalyzer['handler']($file)) {
                    $analysis[$preAnalyzer['name']] = true;
                }
            }
            if (!empty($analysis)) {
                $file->setAnalysis($analysis);

                return false;
            }
        }

        $fileAnalyzers = array_filter($settings, fn (array $setting): bool => !($setting['preAnalyzer'] ?? false));
        if (!empty($fileAnalyzers)) {
            return true;
        }

        $file->setNoAnalysisNeeded();

        return false;
    }

    private function getWorkspaceAnalyzerSettings(Workspace $workspace): array
    {
        // TODO
        return [];
    }
}
