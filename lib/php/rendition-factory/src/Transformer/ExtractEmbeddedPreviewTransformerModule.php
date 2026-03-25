<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use PHPExiftool\Exiftool;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class ExtractEmbeddedPreviewTransformerModule implements TransformerModuleInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return 'extract_embedded_preview';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Extract the embedded preview from a document, if it exists. If the document does not contain an embedded preview, the original document will be returned.
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->arrayNode('eligible_types')
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
                ->ignoreExtraKeys(false)
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $defaultEligibleTypes = [
            'application/illustrator',
        ];

        $eligibleTypes = $options['eligible_types'] ?? $defaultEligibleTypes;

        if (!in_array($inputFile->getType(), $eligibleTypes)) {
            return $inputFile->createOutputFile();
        }

        $exiftool = new Exiftool($this->logger);
        $pathfile = $inputFile->getPath();
        $outputDir = $context->getWorkingDirectory().'/'.uniqid('extracted-preview');
        mkdir($outputDir);
        $realOutPutDir = realpath($outputDir);

        $command = [
            '-if',
            '$photoshopthumbnail',
            '-b',
            '-PhotoshopThumbnail',
            '-w',
            $realOutPutDir.'/PhotoshopThumbnail%c.jpg',
            '-execute',
            '-if',
            '$jpgfromraw',
            '-b',
            '-jpgfromraw',
            '-w',
            $realOutPutDir.'/JpgFromRaw%c.jpg',
            '-execute',
            '-if',
            '$previewimage',
            '-b',
            '-previewimage',
            '-w',
            $realOutPutDir.'/PreviewImage%c.jpg',
            '-execute',
            '-if',
            '$xmp:pageimage',
            '-b',
            '-xmp:pageimage',
            '-w',
            $realOutPutDir.'/XmpPageimage%c.jpg',
            '-execute',
            '-if',
            '$xmp:thumbnailimage',
            '-b',
            '-xmp:thumbnailimage',
            '-w',
            $realOutPutDir.'/XmpThumbnailImage%c.jpg',
            '-common_args',
            '-q',
            '-m',
            $pathfile,
        ];

        try {
            $exiftool->executeCommand($command);
        } catch (\RuntimeException|\Exception $e) {
        }

        $files = new \DirectoryIterator($realOutPutDir);

        $selected = null;
        $size = null;

        foreach ($files as $file) {
            if ($file->isDir() || $file->isDot()) {
                continue;
            }

            if (is_null($selected) || $file->getSize() > $size) {
                $selected = $file->getPathname();
                $size = $file->getSize();
            }
        }

        if ($selected) {
            $this->logger->info(sprintf('Embedded preview found for file %s, returning extracted preview size=%s', $inputFile->getPath(), $size));

            return new OutputFile(
                $selected,
                'image/jpeg',
                FamilyEnum::Image,
                false
            );
        }
        $this->logger->info(sprintf('No embedded preview found for file %s, returning original file', $inputFile->getPath()));

        return $inputFile->createOutputFile();
    }
}
