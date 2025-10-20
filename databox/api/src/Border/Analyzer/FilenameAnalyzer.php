<?php

namespace App\Border\Analyzer;

use App\Entity\Core\File;

final readonly class FilenameAnalyzer implements AnalyzerInterface
{
    public static function getName(): string
    {
        return 'filename';
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        if (!isset($config['pattern'])) {
            return new AnalysisOutput(
                errors: ['FilenameAnalyzer configuration error: "pattern" is not set.']
            );
        }

        if (!preg_match($config['pattern'], $file->getOriginalName())) {
            return new AnalysisOutput(
                errors: [sprintf('Filename "%s" does not match the required pattern.', $file->getOriginalName())]
            );
        }

        return new AnalysisOutput();
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return false;
    }
}
