<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rendition;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\RenditionFactory\Transformer\Image\ContactSheet\ContactSheetGeneratorModule;
use Alchemy\RenditionFactory\Transformer\VoidTransformerModule;
use Imagine\Imagick\Imagine;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ContactSheetGeneratorTest extends TestCase
{
    private const array COLORS = [
        '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffffff', '#808080',
        '#800000', '#008000', '#000080', '#808000', '#800080', '#008080', '#c0c0c0', '#ff8000',
        '#0080ff', '#80ff00', '#ff0080', '#8000ff',
    ];

    private string $workDir;
    private RenditionCreator $creator;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('contact-sheet-test-');
        mkdir($this->workDir);

        $this->creator = new RenditionCreator(
            new TransformationContextFactory(new MimeTypeGuesser(), $this->workDir),
            new FileFamilyGuesser(),
            new ServiceLocator([VoidTransformerModule::NAME => fn () => new VoidTransformerModule()]),
            new ServiceLocator([ContactSheetGeneratorModule::NAME => fn () => new ContactSheetGeneratorModule(new Imagine())]),
        );
    }

    protected function tearDown(): void
    {
        $this->creator->cleanUp();
        exec('rm -rf '.escapeshellarg($this->workDir));
    }

    public function testGenerateSectionIsParsed(): void
    {
        $config = (new YamlLoader())->parse(<<<'YAML'
generate:
    input: preview
    transformations:
        - module: contact_sheet
          options: {grid: 4}
        - module: void
          enabled: false
YAML);

        $generate = $config->getGenerate();
        $this->assertNotNull($generate);
        $this->assertSame('preview', $generate->getInput());
        $this->assertCount(2, $generate->getTransformations());
        $this->assertCount(1, $generate->getEnabledTransformations());
        $this->assertSame('contact_sheet', $generate->getEnabledTransformations()[0]->getModule());
        $this->assertSame(['grid' => 4], $generate->getEnabledTransformations()[0]->getOptions());
        $this->assertNull($config->getFamily(FamilyEnum::Image));

        $this->assertSame(16, $this->creator->getGeneratorInputCount($config));
    }

    public function testGenerateCannotBeCombinedWithFamilies(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new YamlLoader())->parse(<<<'YAML'
generate:
    transformations:
        - module: contact_sheet
image:
    transformations:
        - module: void
YAML);
    }

    public function testFamilyBuildIsNotAGenerateBuild(): void
    {
        $config = $this->parse("image:\n    transformations:\n        - module: void\n");
        $this->assertNull($config->getGenerate());

        $this->expectException(NoBuildConfigException::class);
        $this->creator->generateRendition($config, new CreateRenditionOptions(inputFiles: $this->createInputs(1)));
    }

    public function testGenerateBuildHasNoSourceFamily(): void
    {
        $this->expectException(NoBuildConfigException::class);
        $this->creator->createRendition($this->createImage('#ff0000'), 'image/png', $this->parse($this->sheetDefinition()));
    }

    public function testGrid2WithThreeInputsLeavesTheLastCellToTheBackground(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse($this->sheetDefinition(['background' => '#102030'])),
            new CreateRenditionOptions(inputFiles: $this->createInputs(3)),
        );

        $this->assertSame('image/png', $output->getType());
        $this->assertSame(FamilyEnum::Image, $output->getFamily());
        $this->assertFalse($output->isProjection());
        $this->assertSame([], $output->getBuildHashes());

        $image = new \Imagick($output->getPath());
        $this->assertSame(400, $image->getImageWidth());
        $this->assertSame(400, $image->getImageHeight());

        // Cells of 199px, 2px gap
        $this->assertColor('#ff0000', $image, 100, 100);
        $this->assertColor('#00ff00', $image, 300, 100);
        $this->assertColor('#0000ff', $image, 100, 300);
        $this->assertColor('#102030', $image, 300, 300, 'The missing cell shows the background');
        $this->assertColor('#102030', $image, 199, 100, 'The gap shows the background');
    }

    public function testGrid4UsesTheFirstSixteenInputs(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse($this->sheetDefinition(['grid' => 4, 'gap' => 0])),
            new CreateRenditionOptions(inputFiles: $this->createInputs(20)),
        );

        $image = new \Imagick($output->getPath());
        $this->assertSame(400, $image->getImageWidth());
        for ($i = 0; $i < 16; ++$i) {
            $this->assertColor(self::COLORS[$i], $image, ($i % 4) * 100 + 50, intdiv($i, 4) * 100 + 50, sprintf('Cell %d', $i));
        }
    }

    public function testCoverModeCropsTheImageToFillTheCell(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse($this->sheetDefinition(['gap' => 0, 'mode' => 'cover'])),
            new CreateRenditionOptions(inputFiles: [$this->createInput('#ff0000', 400, 100)]),
        );

        $image = new \Imagick($output->getPath());
        $this->assertColor('#ff0000', $image, 100, 100);
        $this->assertColor('#ff0000', $image, 2, 2, 'The wide image is cropped, the cell is filled');
    }

    public function testContainModeFitsTheWholeImageInTheCell(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse($this->sheetDefinition(['gap' => 0, 'mode' => 'contain'])),
            new CreateRenditionOptions(inputFiles: [$this->createInput('#ff0000', 400, 100)]),
        );

        $image = new \Imagick($output->getPath());
        // 200×50 centered in the 200×200 cell
        $this->assertColor('#ff0000', $image, 100, 100);
        $this->assertColor('#000000', $image, 100, 20, 'Above the image: background');
        $this->assertColor('#000000', $image, 100, 180, 'Below the image: background');
    }

    public function testDefaultFormatIsWebp(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse("generate:\n    transformations:\n        - module: contact_sheet\n"),
            new CreateRenditionOptions(inputFiles: $this->createInputs(4)),
        );

        $this->assertSame('image/webp', $output->getType());
        $this->assertStringEndsWith('.webp', $output->getPath());
        $this->assertSame(400, (new \Imagick($output->getPath()))->getImageWidth());
    }

    public function testFollowingModulesTransformTheSheet(): void
    {
        $output = $this->creator->generateRendition(
            $this->parse($this->sheetDefinition()."        - module: void\n"),
            new CreateRenditionOptions(inputFiles: $this->createInputs(2)),
        );

        $this->assertSame('image/png', $output->getType());
        $this->assertFileExists($output->getPath());
    }

    public function testNoInputIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs at least one input file');

        $this->creator->generateRendition($this->parse($this->sheetDefinition()), new CreateRenditionOptions());
    }

    public function testBuildHashesOfAGenerateBuildAreUnderstood(): void
    {
        $config = $this->parse($this->sheetDefinition());

        // The generator keeps no hash: any recorded one is unknown, hence different
        $this->assertTrue($this->creator->buildHashesDiffer([[BuildConfig::GENERATE, 0, 'x']], $config, new CreateRenditionOptions()));
        $this->assertFalse($this->creator->buildHashesDiffer([], $config, new CreateRenditionOptions()));
    }

    public function testValidatorAcceptsAContactSheet(): void
    {
        $this->createValidator()->validate($this->parse($this->sheetDefinition(['grid' => 4, 'mode' => 'contain', 'quality' => 80])));
        $this->addToAssertionCount(1);
    }

    public function testValidatorRejectsAnInvalidGrid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('contact_sheet');

        $this->createValidator()->validate($this->parse($this->sheetDefinition(['grid' => 3])));
    }

    public function testValidatorRejectsAnUnknownOption(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->createValidator()->validate($this->parse($this->sheetDefinition(['columns' => 3])));
    }

    public function testValidatorRequiresAGeneratorFirst(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a generator');

        $this->createValidator()->validate($this->parse("generate:\n    transformations:\n        - module: void\n"));
    }

    private function createValidator(): BuildConfigValidator
    {
        return new BuildConfigValidator(
            new ServiceLocator([VoidTransformerModule::NAME => fn () => new VoidTransformerModule()]),
            new ServiceLocator([ContactSheetGeneratorModule::NAME => fn () => new ContactSheetGeneratorModule(new Imagine())]),
        );
    }

    private function sheetDefinition(array $options = []): string
    {
        $options = json_encode(array_merge(['format' => 'png'], $options), JSON_THROW_ON_ERROR);

        return <<<YAML
generate:
    transformations:
        - module: contact_sheet
          options: $options

YAML;
    }

    private function parse(string $yaml): BuildConfig
    {
        return (new YamlLoader())->parse($yaml);
    }

    /**
     * @return InputFile[]
     */
    private function createInputs(int $count): array
    {
        return array_map(fn (int $i): InputFile => $this->createInput(self::COLORS[$i]), range(0, $count - 1));
    }

    private function createInput(string $color, int $width = 300, int $height = 300): InputFile
    {
        return new InputFile($this->createImage($color, $width, $height), 'image/png', FamilyEnum::Image);
    }

    private function createImage(string $color, int $width = 300, int $height = 300): string
    {
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel($color));
        $image->setImageFormat('png');

        $path = $this->workDir.'/'.uniqid('input-').'.png';
        $image->writeImage($path);

        return $path;
    }

    private function assertColor(string $expected, \Imagick $image, int $x, int $y, string $message = ''): void
    {
        $color = $image->getImagePixelColor($x, $y)->getColor();
        $actual = sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);

        $this->assertSame($expected, $actual, trim(sprintf('%s (pixel %d,%d)', $message, $x, $y)));
    }
}
