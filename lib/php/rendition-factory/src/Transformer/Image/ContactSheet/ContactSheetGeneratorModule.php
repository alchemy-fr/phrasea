<?php

namespace Alchemy\RenditionFactory\Transformer\Image\ContactSheet;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\UnsupportedSourceFileException;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\GeneratorModuleInterface;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Imagine\Exception\RuntimeException as ImagineRuntimeException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Assembles the input images in a square grid (2×2 or 4×4), in their order.
 * Missing cells are left to the background.
 */
#[AsTaggedItem(index: self::NAME)]
final readonly class ContactSheetGeneratorModule implements GeneratorModuleInterface
{
    public const string NAME = 'contact_sheet';

    public const string MODE_COVER = 'cover';
    public const string MODE_CONTAIN = 'contain';

    private const array FORMATS = [
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(
        private ImagineInterface $imagine,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Generator (first module of a `generate` build): assembles the input images
            in a square grid. The images are placed in their order, the missing cells
            are left to the background.
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('grid')
                        ->info('Number of cells per side: 2 (2×2) or 4 (4×4)')
                        ->values([2, 4])
                        ->defaultValue(2)
                    ->end()
                    ->integerNode('size')
                        ->info('Side of the square output image, in pixels')
                        ->min(16)
                        ->max(4096)
                        ->defaultValue(400)
                    ->end()
                    ->integerNode('gap')
                        ->info('Space between the cells, in pixels')
                        ->min(0)
                        ->max(64)
                        ->defaultValue(2)
                    ->end()
                    ->scalarNode('background')
                        ->info('Background color (HEX), visible in the gaps and the empty cells')
                        ->defaultValue('#000000')
                    ->end()
                    ->enumNode('mode')
                        ->info('"cover" fills each cell and crops the image around its center, "contain" fits the whole image in the cell')
                        ->values([self::MODE_COVER, self::MODE_CONTAIN])
                        ->defaultValue(self::MODE_COVER)
                    ->end()
                    ->enumNode('format')
                        ->info('Output image format')
                        ->values(array_keys(self::FORMATS))
                        ->defaultValue('webp')
                    ->end()
                    ->integerNode('quality')
                        ->info('Output quality, from 1 to 100')
                        ->min(1)
                        ->max(100)
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function getInputCount(array $options): int
    {
        return $this->resolveOptions($options)['grid'] ** 2;
    }

    public function generate(array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $options = $this->resolveOptions($options);
        $grid = $options['grid'];
        $size = $options['size'];
        $gap = $options['gap'];

        $inputFiles = array_slice(array_values($context->getInputFiles()), 0, $grid ** 2);
        if (empty($inputFiles)) {
            throw new \InvalidArgumentException(sprintf('Module "%s" needs at least one input file', self::NAME));
        }

        $palette = new RGB();
        try {
            $background = $palette->color($options['background']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(sprintf('Invalid background color "%s"', $options['background']), 0, $e);
        }

        $sheet = $this->imagine->create(new Box($size, $size), $background);

        foreach ($inputFiles as $i => $inputFile) {
            [$x, $width] = self::getCellSpan($i % $grid, $grid, $size, $gap);
            [$y, $height] = self::getCellSpan(intdiv($i, $grid), $grid, $size, $gap);
            if ($width < 1 || $height < 1) {
                continue;
            }

            $cell = $this->createCell($inputFile, $width, $height, $options['mode']);
            $sheet->paste($cell, new Point(
                $x + intdiv($width - $cell->getSize()->getWidth(), 2),
                $y + intdiv($height - $cell->getSize()->getHeight(), 2),
            ));
        }

        $format = $options['format'];
        $saveOptions = ['format' => $format];
        if (isset($options['quality'])) {
            $saveOptions['quality'] = $options['quality'];
        }

        $outputPath = $context->createTmpFilePath($format);
        $sheet->save($outputPath, $saveOptions);

        return new OutputFile(
            $outputPath,
            self::FORMATS[$format],
            FamilyEnum::Image,
            false,
        );
    }

    /**
     * Cells share the remaining pixels when the size is not a multiple of the grid.
     *
     * @return array{0: int, 1: int} the offset and the length of the cell
     */
    private static function getCellSpan(int $index, int $grid, int $size, int $gap): array
    {
        $step = ($size + $gap) / $grid;
        $start = (int) round($index * $step);
        $end = (int) round(($index + 1) * $step) - $gap;

        return [$start, $end - $start];
    }

    private function createCell(InputFileInterface $inputFile, int $width, int $height, string $mode): ImageInterface
    {
        try {
            $image = $this->imagine->open($inputFile->getPath());
            if (count($image->layers()) > 1) {
                $image = $image->layers()->get(0);
            }

            $imageSize = $image->getSize();
            $ratio = self::MODE_COVER === $mode
                ? max($width / $imageSize->getWidth(), $height / $imageSize->getHeight())
                : min($width / $imageSize->getWidth(), $height / $imageSize->getHeight());

            $scaledWidth = max(1, (int) round($imageSize->getWidth() * $ratio));
            $scaledHeight = max(1, (int) round($imageSize->getHeight() * $ratio));
            $image->resize(new Box($scaledWidth, $scaledHeight));

            if (self::MODE_COVER === $mode) {
                $cropWidth = min($width, $scaledWidth);
                $cropHeight = min($height, $scaledHeight);
                $image->crop(
                    new Point(intdiv($scaledWidth - $cropWidth, 2), intdiv($scaledHeight - $cropHeight, 2)),
                    new Box($cropWidth, $cropHeight),
                );
            }

            return $image;
        } catch (ImagineRuntimeException $e) {
            throw new UnsupportedSourceFileException($inputFile->getPath(), $inputFile->getType(), $e);
        }
    }

    private function resolveOptions(array $options): array
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return (new Processor())->process($treeBuilder->buildTree(), [
            'root' => [
                'module' => self::NAME,
                'options' => $options,
            ],
        ])['options'];
    }
}
