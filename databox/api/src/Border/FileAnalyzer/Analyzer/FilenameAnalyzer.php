<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class FilenameAnalyzer extends AbstractAnalyzer
{
    private const string TYPE_PATTERN_IS_NOT_ALLOWED = 'pattern_is_not_allowed';
    private const string TYPE_PATTERN_IS_DISALLOWED = 'pattern_is_disallowed';
    private const string TYPE_MIME_TYPE_IS_NOT_ALLOWED = 'mime_type_is_not_allowed';
    private const string TYPE_MIME_TYPE_IS_DISALLOWED = 'mime_type_is_disallowed';
    private const string TYPE_EXTENSION_IS_NOT_ALLOWED = 'extension_is_not_allowed';
    private const string TYPE_EXTENSION_IS_DISALLOWED = 'extension_is_disallowed';

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
        $output = new AnalysisOutput();

        $filename = $file->getOriginalName();
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $data = ['extension' => $extension];

        if (!empty($config['disallowed_extensions']) && in_array($extension, $config['disallowed_extensions'], true)) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_EXTENSION_IS_DISALLOWED);
        }

        if (!empty($config['allowed_extensions']) && !in_array($extension, $config['allowed_extensions'], true)) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_EXTENSION_IS_NOT_ALLOWED, [
                'allowed' => $config['allowed_extensions'],
            ]);
        }

        $mimeType = $file->getType();
        if (!empty($config['disallowed_mime_types']) && in_array($mimeType, $config['disallowed_mime_types'], true)) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MIME_TYPE_IS_DISALLOWED);
        }
        if (!empty($config['allowed_mime_types']) && !in_array($mimeType, $config['allowed_mime_types'], true)) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MIME_TYPE_IS_NOT_ALLOWED, [
                'allowed' => $config['allowed_mime_types'],
            ]);
        }

        if (!empty($config['disallowed_patterns'])) {
            foreach ($config['disallowed_patterns'] as $disallowedPattern) {
                if (preg_match($disallowedPattern, (string) $filename)) {
                    $output->addMessage(LogLevelEnum::Critical, self::TYPE_PATTERN_IS_DISALLOWED, [
                        'disallowed_pattern' => $disallowedPattern,
                    ]);
                }
            }
        }

        if (!empty($config['allowed_patterns'])) {
            $matchesAllowed = false;
            foreach ($config['allowed_patterns'] as $allowedPattern) {
                if (preg_match($allowedPattern, (string) $filename)) {
                    $matchesAllowed = true;
                    break;
                }
            }
            if (!$matchesAllowed) {
                $output->addMessage(LogLevelEnum::Critical, self::TYPE_PATTERN_IS_NOT_ALLOWED, [
                    'allowed' => $config['allowed_patterns'],
                ]);
            }
        }

        $output->setData($data);

        return $output;
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return false;
    }

    protected function getDocumentationHeader(): string
    {
        return 'Analyzes the filename of the file and checks it against allowed/disallowed patterns, extensions, and MIME types.';
    }
}
