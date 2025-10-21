<?php

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class FilenameAnalyzer extends AbstractAnalyzer
{
    public static function getName(): string
    {
        return 'filename';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->arrayNode('allowed_patterns')
                ->info('One or more regex patterns that the filename can match.')
            ->prototype('scalar')->end()
            ->end()
            ->arrayNode('disallowed_patterns')
                ->info('One or more regex patterns that the filename cannot match.')
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('allowed_extensions')
                ->info('One or more file extensions that are allowed.')
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('disallowed_extensions')
                ->info('One or more file extensions that are not allowed.')
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('allowed_mime_types')
                ->info('One or more MIME types that are allowed.')
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('disallowed_mime_types')
                ->info('One or more MIME types that are not allowed.')
                ->prototype('scalar')->end()
            ->end()
        ;
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $filename = $file->getOriginalName();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!empty($config['disallowed_extensions']) && in_array($extension, $config['disallowed_extensions'], true)) {
            return new AnalysisOutput(
                errors: [sprintf('File extension "%s" is disallowed.', $extension)]
            );
        }

        if (!empty($config['allowed_extensions']) && !in_array($extension, $config['allowed_extensions'], true)) {
            return new AnalysisOutput(
                errors: [sprintf('File extension "%s" is not allowed.', $extension)]
            );
        }

        $mimeType = $file->getType();
        if (!empty($config['disallowed_mime_types']) && in_array($mimeType, $config['disallowed_mime_types'], true)) {
            return new AnalysisOutput(
                errors: [sprintf('MIME type "%s" is disallowed.', $mimeType)]
            );
        }
        if (!empty($config['allowed_mime_types']) && !in_array($mimeType, $config['allowed_mime_types'], true)) {
            return new AnalysisOutput(
                errors: [sprintf('MIME type "%s" is not allowed.', $mimeType)]
            );
        }

        if (!empty($config['disallowed_patterns'])) {
            foreach ($config['disallowed_patterns'] as $disallowedPattern) {
                if (preg_match($disallowedPattern, $filename)) {
                    return new AnalysisOutput(
                        errors: [sprintf('Filename "%s" matches a disallowed pattern.', $filename)]
                    );
                }
            }
        }

        if (!empty($config['allowed_patterns'])) {
            $matchesAllowed = false;
            foreach ($config['allowed_patterns'] as $allowedPattern) {
                if (preg_match($allowedPattern, $filename)) {
                    $matchesAllowed = true;
                    break;
                }
            }
            if (!$matchesAllowed) {
                return new AnalysisOutput(
                    errors: [sprintf('Filename "%s" does not match any allowed patterns.', $filename)]
                );
            }
        }

        return new AnalysisOutput();
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return false;
    }
}
