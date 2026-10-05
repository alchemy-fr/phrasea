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
                ->info('One or more regex patterns that the filename must match (e.g. "^PHOTO_.*"). Delimiters are optional.')
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('disallowed_patterns')
                ->info('One or more regex patterns that the filename cannot match (e.g. "\\.tmp$"). Delimiters are optional.')
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

    public function validateConfiguration(array $config): void
    {
        foreach (['allowed_patterns', 'disallowed_patterns'] as $key) {
            foreach ($config[$key] ?? [] as $pattern) {
                $this->normalizePattern((string) $pattern, $key);
            }
        }
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
                if (preg_match($this->normalizePattern((string) $disallowedPattern, 'disallowed_patterns'), (string) $filename)) {
                    $output->addMessage(LogLevelEnum::Critical, self::TYPE_PATTERN_IS_DISALLOWED, [
                        'disallowed_pattern' => $disallowedPattern,
                    ]);
                }
            }
        }

        if (!empty($config['allowed_patterns'])) {
            $matchesAllowed = false;
            foreach ($config['allowed_patterns'] as $allowedPattern) {
                if (preg_match($this->normalizePattern((string) $allowedPattern, 'allowed_patterns'), (string) $filename)) {
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

    /**
     * Returns a pattern usable by preg_match().
     *
     * Patterns written without delimiters (e.g. "^PHOTO_.*") are wrapped in "/" delimiters;
     * patterns that are invalid even after wrapping raise an exception instead of silently
     * never matching (preg_match() returns false on an invalid pattern).
     */
    private function normalizePattern(string $pattern, string $option): string
    {
        if (false !== @preg_match($pattern, '')) {
            return $pattern;
        }

        $wrapped = '/'.str_replace('/', '\\/', $pattern).'/';
        if (false !== @preg_match($wrapped, '')) {
            return $wrapped;
        }

        throw new \InvalidArgumentException(sprintf('Invalid regex pattern "%s" in "%s": %s', $pattern, $option, preg_last_error_msg()));
    }

    protected function getDocumentationHeader(): string
    {
        return 'Analyzes the filename of the file and checks it against allowed/disallowed patterns, extensions, and MIME types.';
    }
}
