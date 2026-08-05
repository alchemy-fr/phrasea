<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Analyzer;

use Alchemy\RenditionFactory\Image\ColorspaceDetector;
use App\Border\FileAnalyzer\AbstractAnalyzer;
use App\Border\FileAnalyzer\Dto\AnalysisOutput;
use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Entity\Core\File;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ImageColorspaceAnalyzer extends AbstractAnalyzer
{
    private const array COMMON_COLORSPACES = [
        ColorspaceDetector::CS_RGB,
        ColorspaceDetector::CS_SRGB,
        ColorspaceDetector::CS_CMYK,
        ColorspaceDetector::CS_GRAYSCALE,
        ColorspaceDetector::CS_YCBCR,
        ColorspaceDetector::CS_YCCK,
        ColorspaceDetector::CS_PALETTE,
        ColorspaceDetector::CS_LAB,
        ColorspaceDetector::CS_UNKNOWN,
    ];

    private const string TYPE_DISALLOWED_COLORSPACE = 'disallowed_colorspace';
    private const string TYPE_NOT_IN_ALLOWED_COLORSPACES = 'not_in_allowed_colorspaces';
    private const string TYPE_UNKNOWN_COLORSPACE = 'unknown_colorspace';
    private const string TYPE_NOT_AN_IMAGE = 'not_an_image';

    public static function getName(): string
    {
        return 'image_colorspace';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('allowed_colorspaces')
                ->info(sprintf('One or more colorspaces that are allowed (%s).', implode(', ', self::COMMON_COLORSPACES)))
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('disallowed_colorspaces')
                ->info('One or more colorspaces that are not allowed.')
                ->scalarPrototype()->end()
            ->end()
        ;
        // @formatter:on
    }

    public function validateConfiguration(array $config): void
    {
        $allowedSpaces = array_map('strtolower', $config['allowed_colorspaces'] ?? []);
        $disallowedSpaces = array_map('strtolower', $config['disallowed_colorspaces'] ?? []);

        // Check for invalid colorspace names
        $validSpaces = array_keys(self::COMMON_COLORSPACES);
        foreach (array_merge($allowedSpaces, $disallowedSpaces) as $space) {
            if (!in_array($space, $validSpaces, true)) {
                throw new \InvalidArgumentException(sprintf('Unknown colorspace "%s". Valid options are: %s', $space, implode(', ', $validSpaces)));
            }
        }

        // Check for conflicting configurations
        if (!empty($allowedSpaces) && !empty($disallowedSpaces)) {
            $intersection = array_intersect($allowedSpaces, $disallowedSpaces);
            if (!empty($intersection)) {
                throw new \InvalidArgumentException(sprintf('Colorspaces cannot be in both allowed and disallowed lists: %s', implode(', ', $intersection)));
            }
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

        $colorspaceDetector = new ColorspaceDetector();
        $colorspace = $colorspaceDetector->detect($path);

        $data = ['colorspace' => $colorspace];

        if (ColorspaceDetector::CS_UNKNOWN === $colorspace) {
            $output->addMessage(LogLevelEnum::Error, self::TYPE_UNKNOWN_COLORSPACE);
        } else {
            if (!empty($config['disallowed_colorspaces'])) {
                $disallowedSpaces = array_map('strtolower', $config['disallowed_colorspaces']);
                if (in_array($colorspace, $disallowedSpaces, true)) {
                    $output->addMessage(LogLevelEnum::Critical, self::TYPE_DISALLOWED_COLORSPACE, [
                        'disallowed' => $disallowedSpaces,
                    ]);
                }
            }

            if (!empty($config['allowed_colorspaces'])) {
                $allowedSpaces = array_map('strtolower', $config['allowed_colorspaces']);
                if (!in_array($colorspace, $allowedSpaces, true)) {
                    $output->addMessage(LogLevelEnum::Critical, self::TYPE_NOT_IN_ALLOWED_COLORSPACES, [
                        'allowed' => $allowedSpaces,
                    ]);
                }
            }
        }

        $output->setData($data);

        return $output;
    }

    public function requiresFileContent(File $file, array $config): bool
    {
        return str_starts_with((string) $file->getType(), 'image/');
    }

    protected function getDocumentationHeader(): string
    {
        return 'Analyzes the colorspace of an image file and rejects it if its colorspace is not in the allowed list or is in the disallowed list. Non-image files are skipped.';
    }
}
