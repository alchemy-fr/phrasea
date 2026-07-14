<?php

declare(strict_types=1);

namespace App\Border\Analyzer;

use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ImageDimensionAnalyzer extends AbstractAnalyzer
{
    public static function getName(): string
    {
        return 'image_dimension';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->integerNode('min_width')
                ->min(1)
                ->info('Minimum image width in pixels (inclusive).')
            ->end()
            ->integerNode('max_width')
                ->min(1)
                ->info('Maximum image width in pixels (inclusive).')
            ->end()
            ->integerNode('min_height')
                ->min(1)
                ->info('Minimum image height in pixels (inclusive).')
            ->end()
            ->integerNode('max_height')
                ->min(1)
                ->info('Maximum image height in pixels (inclusive).')
            ->end()
        ;
        // @formatter:on
    }

    public function validateConfiguration(array $config): void
    {
        if (isset($config['min_width'], $config['max_width']) && $config['min_width'] > $config['max_width']) {
            throw new \InvalidArgumentException(sprintf('min_width (%d) cannot be greater than max_width (%d).', $config['min_width'], $config['max_width']));
        }

        if (isset($config['min_height'], $config['max_height']) && $config['min_height'] > $config['max_height']) {
            throw new \InvalidArgumentException(sprintf('min_height (%d) cannot be greater than max_height (%d).', $config['min_height'], $config['max_height']));
        }
    }

    public function analyzeFile(File $file, ?string $path, array $config): AnalysisOutput
    {
        $mimeType = $file->getType();

        // Skip non-image files
        if (!str_starts_with((string) $mimeType, 'image/')) {
            return new AnalysisOutput(
                logs: [sprintf('File with MIME type "%s" is not an image; skipping dimension analysis.', $mimeType)]
            );
        }

        if (empty($path) || !file_exists($path)) {
            return new AnalysisOutput(
                errors: ['File path is required for image dimension analysis.']
            );
        }

        $imageSize = @getimagesize($path);
        if (false === $imageSize) {
            return new AnalysisOutput(
                errors: ['Could not read image dimensions from file.']
            );
        }

        [$width, $height] = $imageSize;

        $errors = [];

        if (isset($config['min_width']) && $width < $config['min_width']) {
            $errors[] = sprintf(
                'Image width %dpx is below the minimum allowed width of %dpx.',
                $width,
                $config['min_width']
            );
        }

        if (isset($config['max_width']) && $width > $config['max_width']) {
            $errors[] = sprintf(
                'Image width %dpx exceeds the maximum allowed width of %dpx.',
                $width,
                $config['max_width']
            );
        }

        if (isset($config['min_height']) && $height < $config['min_height']) {
            $errors[] = sprintf(
                'Image height %dpx is below the minimum allowed height of %dpx.',
                $height,
                $config['min_height']
            );
        }

        if (isset($config['max_height']) && $height > $config['max_height']) {
            $errors[] = sprintf(
                'Image height %dpx exceeds the maximum allowed height of %dpx.',
                $height,
                $config['max_height']
            );
        }

        return new AnalysisOutput(
            errors: $errors,
            data: [
                'width' => $width,
                'height' => $height,
            ]
        );
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return str_starts_with((string) $file->getType(), 'image/');
    }

    protected function getDocumentationHeader(): string
    {
        return 'Analyzes the dimensions of an image file and rejects it if its width or height falls outside the configured minimum/maximum bounds. Non-image files are skipped.';
    }
}
