<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine;

use Liip\ImagineBundle\Model\FileBinary;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\MimeType\ImageFormatGuesser;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Alchemy\RenditionFactory\Transformer\BuildHashDiffInterface;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;

final readonly class ImagineTransformerModule implements TransformerModuleInterface, BuildHashDiffInterface
{
    public function __construct(
        private ImagineFilterFactory $filterFactory,
    ) {
    }

    public static function getName(): string
    {
        return 'imagine';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        $doc =  new Documentation(
            $treeBuilder,
            <<<HEADER
            Transform an image with some filter.
            HEADER
        );

        return $doc;
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->scalarNode('format')
                        ->info('output image format')
                        ->example('jpeg')
                    ->end()
                    ->arrayNode('filters')
                        ->info('Filters to apply to the image')
                        ->children()
                            ->arrayNode('relative_resize')
                                ->info('Filter performs sizing transformations (specifically relative resizing)')
                                ->children()
                                    ->scalarNode('heighten')
                                        ->example('value "60" => given 50x40px, output 75x60px using "heighten" option')
                                    ->end()
                                    ->scalarNode('widen')
                                        ->example('value "32" => given 50x40px, output 32x26px using "widen" option')
                                    ->end()
                                    ->scalarNode('increase')
                                        ->example('value "10" => given 50x40px, output 60x50px, using "increase" option')
                                    ->end()
                                    ->scalarNode('scale')
                                        ->example('value "2.5" => given 50x40px, output 125x100px using "scale" option')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('resize')
                                ->info('use and setup the resize filter')
                                ->children()
                                    ->arrayNode('size')
                                        ->info('set the size of the resizing area [width,height]')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(120)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(90)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('thumbnail')
                                ->info('Filter performs thumbnail transformations (which includes scaling and potentially cropping operations)')
                                ->children()
                                    ->arrayNode('size')
                                        ->info('set the thumbnail size to [width,height] pixels')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(32)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(32)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('mode')
                                        ->info('Sets the desired resize method: "outbound" crops the image as required, while "inset" performs a non-cropping relative resize.')
                                        ->example('inset')
                                    ->end()
                                    ->booleanNode('allow_upscale')
                                        ->info('Toggles allowing image up-scaling when the image is smaller than the desired thumbnail size. Value: true or false')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('crop')
                                ->info('filter performs sizing transformations (which includes cropping operations)')
                                ->children()
                                    ->arrayNode('size')
                                        ->info('set the size of the cropping area [width,height]')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(300)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(600)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('start')
                                        ->info('Sets the top, left-post anchor coordinates where the crop operation starts[x, y]')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(040)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(160)
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('watermark')
                                ->info('filter adds a watermark to an existing image')
                                ->children()
                                    ->scalarNode('image')
                                        ->info('Path to the watermark image')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('background_fill')
                                ->info('filter fill background color')
                                ->children()
                                    ->scalarNode('color')
                                        ->info('Sets the background color HEX value. The default color is white (#fff).')
                                        ->end()
                                    ->integerNode('opacity')
                                        ->info('Sets the background opacity. The value should be within a range of 0 (fully transparent) - 100 (opaque). default opacity 100')
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('strip')
                                ->info(' filter performs file transformations (which includes metadata removal)')
                            ->end()
                            ->arrayNode('scale')
                                ->info('filter performs sizing transformations (specifically image scaling)')
                                ->children()
                                    ->arrayNode('dim')
                                        ->info('Sets the "desired dimensions" [width, height], from which a relative resize is performed within these constraints.')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(800)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(1000)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->floatNode('to')
                                        ->info('Sets the "ratio multiple" which initiates a proportional scale operation computed by multiplying all image sides by this value.')
                                        ->example(1.5)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('upscale')
                                ->info(' filter performs sizing transformations (specifically image up-scaling)')
                                ->children()
                                    ->arrayNode('min')
                                        ->info('Sets the "desired min dimensions" [width, height], from which an up-scale is performed to meet the passed constraints.')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(1200)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(800)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->floatNode('by')
                                        ->info('Sets the "ratio multiple" which initiates a proportional scale operation computed by multiplying all image sides by this value.')
                                        ->example(0.7)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('downscale')
                                ->info('filter performs sizing transformations (specifically image down-scaling)')
                                ->children()
                                    ->arrayNode('max')
                                        ->info('Sets the "desired max dimensions" [width, height], from which a down-scale is performed to meet the passed constraints')
                                        ->children()
                                            ->integerNode(0)
                                                ->example(1980)
                                            ->end()
                                            ->integerNode(1)
                                                ->example(1280)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->floatNode('by')
                                        ->info('Sets the "ratio multiple" which initiates a proportional scale operation computed by multiplying all image sides by this value.')
                                        ->example(0.6)
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('auto_rotate')
                                ->info('filter performs orientation transformations (which includes rotating the image)')
                            ->end()
                            ->arrayNode('rotate')
                                ->info('filter performs orientation transformations (specifically image rotation)')
                                ->children()
                                    ->floatNode('angle')
                                        ->info('Sets the rotation angle in degrees. The default value is 0.')
                                        ->example(90)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('flip')
                                ->info('filter performs orientation transformations (specifically image flipping)')
                                ->children()
                                    ->scalarNode('axis')
                                        ->info('Sets the "flip axis" that defines the axis on which to flip the image. Valid values: x, horizontal, y, vertical')
                                        ->example('x')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('interlace')
                                ->info('filter performs file transformations (which includes modifying the encoding method)')
                                ->children()
                                    ->scalarNode('mode')
                                        ->info('Sets the interlace mode to encode the file with. Valid values: none, line, plane, and partition.')
                                        ->example('line')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('resample')
                                ->info('filter provides a resampling transformation by allows you to change the resolution of an image')
                                ->children()
                                    ->scalarNode('unit')
                                        ->info('Sets the unit to use for pixel density, either "pixels per inch" or "pixels per centimeter". Valid values: ppi and ppc')
                                        ->example('ppi')
                                    ->end()
                                    ->floatNode('x')
                                        ->info('Sets the horizontal resolution in the specified unit')
                                        ->example(300)
                                    ->end()
                                    ->floatNode('y')
                                        ->info('Sets the vertical resolution in the specified unit')
                                        ->example(200)
                                    ->end()
                                    ->scalarNode('tmp_dir')
                                        ->info('Sets the optional temporary work directory. This filter requires a temporary location to save out and read back in the image binary, as these operations are requires to resample an image. By default, it is set to the value of the sys_get_temp_dir() function')
                                        ->example('/my/custom/temporary/directory/path')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('fixed')
                                ->info('filter performs thumbnail transformations (which includes scaling and potentially cropping operations)')
                                ->children()
                                    ->integerNode('width')
                                        ->info('Sets the "desired width" which initiates a proportional scale operation that up- or down-scales until the image width matches this value.')
                                        ->example(120)
                                    ->end()
                                    ->integerNode('height')
                                        ->info('Sets the "desired height" which initiates a proportional scale operation that up- or down-scales until the image height matches this value')
                                        ->example(90)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('stamp')
                                ->info('use stamp')
                                ->children()
                                    ->scalarNode('font')
                                        ->info('path to the font file ttf')
                                    ->end()
                                    ->scalarNode('position')
                                        ->info('available position value: topleft, top, topright, left, center, right, bottomleft, bottom, bottomright, under, above')
                                    ->end()
                                    ->integerNode('angle')
                                        ->defaultValue(0)
                                    ->end()
                                    ->integerNode('size')
                                        ->info ('font size')
                                        ->defaultValue(16)
                                    ->end()
                                    ->scalarNode('color')
                                        ->defaultValue('#000000')
                                    ->end()
                                    ->integerNode('alpha')
                                        ->info('the font alpha value')
                                        ->defaultValue(100)
                                    ->end()
                                    ->scalarNode('text')
                                        ->info('text to stamp')
                                    ->end()
                                    ->integerNode('width')
                                        ->info('text width')
                                    ->end()
                                    ->scalarNode('background')
                                        ->info('text background, option use for position under or above')
                                        ->defaultValue('#FFFFFF')
                                    ->end()
                                    ->integerNode('transparency')
                                        ->info('background transparancy, option use for position under or above')
                                        ->defaultValue(null)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $inputFormat = ImageFormatGuesser::getFormat($inputFile->getType());
        if ('svg' === $inputFormat) {
            return $inputFile->createOutputFile();
        }

        $options['format'] ??= $inputFormat;
        $options['filters'] = $this->normalizeFilters($options['filters'] ?? []);

        $filterManager = $this->filterFactory->createFilterManager($context);

        $t = explode('/', $inputFile->getType());
        $type = (2 === count($t) && 'image' === $t[0]) ? $t[1] : null;

        $image = new FileBinary($inputFile->getPath(), $inputFile->getType(), $type);
        $output = $filterManager->apply($image, $options);

        $extension = $output->getFormat();
        if (empty($extension)) {
            $extension = $context->getExtension($output->getMimeType());
        }

        $outputPath = $context->createTmpFilePath($extension);
        file_put_contents($outputPath, $output->getContent());

        return new OutputFile(
            $outputPath,
            $output->getMimeType(),
            FamilyEnum::Image,
            false // TODO implement projection
        );
    }

    private function normalizeFilters(array $filters): array
    {
        foreach ($filters as $filter => $options) {
            $filters[$filter] = $options ?? [];
        }

        return $filters;
    }

    public function buildHashesDiffer(array $buildHashes, array $options, TransformationContextInterface $transformationContext): bool
    {
        $filterLoaders = $this->filterFactory->createFilterLoaders($transformationContext);

        if (!empty($buildHashes)) {
            $filterName = array_shift($buildHashes);

            $filters = $this->normalizeFilters($options['filters'] ?? []);
            if (!isset($filters[$filterName])) {
                return true;
            }

            $filter = $filterLoaders[$filterName] ?? null;
            if (!$filter instanceof BuildHashDiffInterface) {
                return true;
            }
            if ($filter->buildHashesDiffer($buildHashes, $filters[$filterName], $transformationContext)) {
                return true;
            }
        }

        return false;
    }
}
