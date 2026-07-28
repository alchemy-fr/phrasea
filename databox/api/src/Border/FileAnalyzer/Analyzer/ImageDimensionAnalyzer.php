<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ImageDimensionAnalyzer extends AbstractAnalyzer
{
    private const string TYPE_NOT_AN_IMAGE = 'not_an_image';
    public const string TYPE_MIN_WIDTH = 'min_width';
    public const string TYPE_MAX_WIDTH = 'max_width';
    public const string TYPE_MIN_HEIGHT = 'min_height';
    public const string TYPE_MAX_HEIGHT = 'max_height';

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
        $output = new AnalysisOutput();
        $mimeType = $file->getType();

        if (!str_starts_with((string) $mimeType, 'image/')) {
            $output->addMessage(LogLevelEnum::Debug, self::TYPE_NOT_AN_IMAGE);

            return $output;
        }

        if (empty($path) || !file_exists($path)) {
            throw new \RuntimeException(sprintf('File "%s" with MIME type "%s" does not exist', $path, $mimeType));
        }

        $imageSize = @getimagesize($path);
        if (false === $imageSize) {
            $output->addMessage(LogLevelEnum::Critical, 'unreadable_image');

            return $output;
        }

        [$width, $height] = $imageSize;

        $errors = [];

        $minWidth = $config['min_width'] ?? null;
        if (null !== $minWidth && $width < $minWidth) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MIN_WIDTH, [
                'min_width' => $minWidth,
                'width' => $width,
            ]);
        }

        $maxWidth = $config['max_width'] ?? null;
        if (null !== $maxWidth && $width > $maxWidth) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MAX_WIDTH, [
                'max_width' => $maxWidth,
                'width' => $width,
            ]);
        }

        $minHeight = $config['min_height'] ?? null;
        if (null !== $minHeight && $height < $minHeight) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MIN_HEIGHT, [
                'min_height' => $minHeight,
                'height' => $height,
            ]);
        }

        $maxHeight = $config['max_height'] ?? null;
        if (null !== $maxHeight && $height > $maxHeight) {
            $output->addMessage(LogLevelEnum::Critical, self::TYPE_MAX_HEIGHT, [
                'max_height' => $maxHeight,
                'height' => $height,
            ]);
        }

        $output->setData([
            'width' => $width,
            'height' => $height,
        ]);

        return $output;
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
