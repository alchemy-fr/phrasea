<?php

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: self::TAG)]
interface AnalyzerInterface
{
    final public const string TAG = 'app.file_analyzer';

    public static function getName(): string;

    /**
     * @param string|null $path The local path to the file content, if available
     */
    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput;

    public function requiresFileContent(File $file, array $config): bool;
}
