<?php

declare(strict_types=1);

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DebugAnalyzer extends AbstractAnalyzer
{
    public static function getName(): string
    {
        return 'debug';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('errors')
                ->info('If empty, file will be accepted.')
                ->scalarPrototype()
                ->end()
            ->end()
            ->arrayNode('warnings')
                ->scalarPrototype()
                ->end()
            ->end()
            ->arrayNode('data')
                ->variablePrototype()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        return new AnalysisOutput(
            errors: $config['errors'],
            warnings: $config['warnings'],
            data: $config['data']
        );
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return true;
    }

    public function validateConfiguration(array $config): void
    {
    }

    protected function getDocumentationHeader(): string
    {
        return 'This analyzer is used for debugging purposes. It allows you to specify errors, warnings, and additional data to be returned during the analysis of a file.';
    }
}
