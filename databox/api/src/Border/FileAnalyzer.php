<?php

declare(strict_types=1);

namespace App\Border;

use App\Entity\Core\File;
use App\Service\Asset\FileFetcher;

final readonly class FileAnalyzer
{
    public function __construct(
        private FileAnalyzerRegistry $fileAnalyzerRegistry,
        private FileFetcher $fileFetcher,
    ) {
    }

    public function analyzeFile(File $file, array $config, bool $force = false): void
    {
        if ($file->isAnalyzed() && !$force) {
            return;
        }

        if (File::STORAGE_S3_MAIN !== $file->getStorage()) {
            $file->setAnalysis([
                'status' => File::ANALYSIS_SKIPPED,
                'message' => 'File analysis skipped because not stored into Databox.',
            ]);

            return;
        }

        $filePath = $this->fileFetcher->getFile($file);
        try {
            $this->analyzeFileSource($filePath, $file, $config);
        } finally {
            @unlink($filePath);
        }
    }

    public function analyzeFileSource(string $filePath, File $file, array $config): void
    {
        $outputs = [];
        foreach ($config['analyzers'] ?? [] as $analyzerConfig) {
            $analyzer = $this->fileAnalyzerRegistry->getAnalyzer($analyzerConfig['name']);
            $analyzerConfig = $this->fileAnalyzerRegistry->processConfiguration(
                $analyzer,
                $analyzerConfig,
            );

            $output = $analyzer->analyzeFile($file, $filePath, $analyzerConfig);
            $outputs[] = [
                'name' => $analyzerConfig['name'],
                'output' => $output->toArray(),
            ];
            if (!$output->isSuccessful()) {
                $file->setAnalysis([
                    'status' => File::ANALYSIS_FAILED,
                    'results' => $outputs,
                ]);

                return;
            }
        }

        $file->setAnalysis([
            'status' => File::ANALYSIS_SUCCESS,
            'results' => $outputs,
        ]);
    }

    /**
     * @return bool Whether to proceed File analysis
     */
    public function preAnalyzeFile(File $file, array $config, bool $force = false): bool
    {
        if ($file->isAnalyzed() && !$force) {
            return false;
        }

        $outputs = [];
        $fileContentsRequired = false;

        foreach ($config['analyzers'] ?? [] as $analyzerConfig) {
            $analyzer = $this->fileAnalyzerRegistry->getAnalyzer($analyzerConfig['name']);

            if ($analyzer->requiresFileContent($file, $analyzerConfig)) {
                $fileContentsRequired = true;

                continue;
            }

            $output = $analyzer->analyzeFile($file, null, $analyzerConfig);
            $outputs[] = [
                'name' => $analyzerConfig['name'],
                'output' => $output->toArray(),
            ];
            if (!$output->isSuccessful()) {
                $file->setAnalysis($outputs);

                return false;
            }
        }

        if (!$fileContentsRequired) {
            $file->setAnalysis([
                'status' => File::ANALYSIS_SUCCESS,
                'results' => $outputs,
            ]);
        }

        return $fileContentsRequired;
    }
}
