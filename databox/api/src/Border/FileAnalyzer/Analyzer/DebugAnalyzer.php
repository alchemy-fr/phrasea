<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
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
            ->arrayNode('messages')
                ->info('If no error or critical message is defined, then file will be accepted.')
                ->arrayPrototype()
                    ->children()
                        ->enumNode('level')->values(LogLevelEnum::getNames())->end()
                        ->scalarNode('type')->end()
                        ->arrayNode('payload')
                            ->variablePrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('data')
                ->variablePrototype()
                ->end()
            ->end()
            ->arrayNode('duplicates')
                ->scalarPrototype()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $output = new AnalysisOutput(
            data: $config['data'] ?? [],
            duplicates: $config['duplicates'] ?? [],
        );

        foreach ($config['messages'] ?? [] as $error) {
            $output->addMessage(LogLevelEnum::fromLabel($error['level'] ?? LogLevelEnum::Info->name), $error['type'], $error['payload'] ?? []);
        }

        return $output;
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
