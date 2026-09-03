<?php

declare(strict_types=1);

namespace App\Border;

use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Border\FileAnalyzer\FileAnalyzerConfigHelper;
use App\Border\FileAnalyzer\FileDuplicateManager;
use App\Entity\Core\File;
use App\Service\Asset\FileFetcher;

final readonly class FileAnalyzer
{
    public function __construct(
        private FileAnalyzerRegistry $fileAnalyzerRegistry,
        private FileFetcher $fileFetcher,
        private FileDuplicateManager $fileDuplicateManager,
    ) {
    }

    public function shouldAnalyze(File $file, array $config, bool $force = false): bool
    {
        if ($force || !$file->isAnalyzed()) {
            return true;
        }

        $hash = $file->getAnalysis()['hash'] ?? null;
        if (null === $hash) {
            return true;
        }

        $newHash = $this->computeHash($file, $config);

        return $hash !== $newHash;
    }

    private function computeHash(File $file, array $config): string
    {
        return md5($file->getId().':'.serialize($config));
    }

    public function analyzeFile(File $file, array $config): void
    {
        if (File::STORAGE_S3_MAIN !== $file->getStorage()) {
            $file->setAnalysis([
                'status' => File::ANALYSIS_SKIPPED,
                'message' => 'File analysis skipped because not stored into Databox.',
            ]);
            $this->fileDuplicateManager->replaceDuplicates($file, []);

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
        $duplicatesByAnalyzer = [];
        $status = File::ANALYSIS_SUCCESS;
        foreach ($config['analyzers'] ?? [] as $analyzerConfig) {
            $analyzer = $this->fileAnalyzerRegistry->getAnalyzer($analyzerConfig['name']);
            $analyzerConfig = $this->fileAnalyzerRegistry->processConfiguration(
                $analyzer,
                $analyzerConfig,
            );

            $output = $this->limitSeverity($analyzer->analyzeFile($file, $filePath, $analyzerConfig), $analyzerConfig);
            $outputs[] = $this->getOutputData($output, $analyzerConfig);
            $duplicatesByAnalyzer[$analyzerConfig['name']] = $output->getDuplicates();
            if (!$output->isSuccessful()) {
                $status = File::ANALYSIS_FAILED;
            }
        }

        $this->assignAnalysis($file, $status, $outputs, $config, $duplicatesByAnalyzer);
    }

    private function assignAnalysis(File $file, string $status, array $outputs, array $config, array $duplicatesByAnalyzer): void
    {
        $file->setAnalysis([
            'status' => $status,
            'results' => $outputs,
            'hash' => $this->computeHash($file, $config),
        ]);

        $this->fileDuplicateManager->replaceDuplicates($file, $duplicatesByAnalyzer);
    }

    private function limitSeverity(AnalysisOutput $analysisOutput, array $config): AnalysisOutput
    {
        return $analysisOutput->limitSeverity(LogLevelEnum::fromLabel($config[FileAnalyzerConfigHelper::MAX_SEVERITY]));
    }

    /**
     * @return bool Whether to proceed File analysis
     */
    public function preAnalyzeFile(File $file, array $config): bool
    {
        $outputs = [];
        $duplicatesByAnalyzer = [];
        $status = File::ANALYSIS_SUCCESS;

        foreach ($config['analyzers'] ?? [] as $analyzerConfig) {
            $analyzer = $this->fileAnalyzerRegistry->getAnalyzer($analyzerConfig['name']);
            $analyzerConfig = $this->fileAnalyzerRegistry->processConfiguration(
                $analyzer,
                $analyzerConfig,
            );

            if ($analyzer->requiresFileContent($file, $analyzerConfig)) {
                return true;
            }

            $output = $analyzer->analyzeFile($file, null, $analyzerConfig);

            $outputs[] = $this->getOutputData($output, $analyzerConfig);
            $duplicatesByAnalyzer[$analyzerConfig['name']] = $output->getDuplicates();
            if (!$output->isSuccessful()) {
                $status = File::ANALYSIS_FAILED;
            }
        }

        $this->assignAnalysis($file, $status, $outputs, $config, $duplicatesByAnalyzer);

        return false;
    }

    private function getOutputData(AnalysisOutput $output, array $analyzerConfig): array
    {
        $data = [
            'name' => $analyzerConfig['name'],
            'output' => $output->toArray(),
        ];
        if (!empty($analyzerConfig['actions_on_reject'])) {
            $data['actions'] = $analyzerConfig['actions_on_reject'];
        }

        return $data;
    }
}
