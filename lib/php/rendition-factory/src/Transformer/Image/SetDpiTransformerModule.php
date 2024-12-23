<?php

namespace Alchemy\RenditionFactory\Transformer\Image;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
// use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Exiftool;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;


final readonly class SetDpiTransformerModule implements TransformerModuleInterface
{
    public function __construct(private LoggerInterface $logger) {
    }

    public static function getName(): string
    {
        return 'set_dpi';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Change the dpi metadata of an image (no resampling).
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->IntegerNode('dpi')
                        ->isRequired()
                        ->example('72')
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if($inputFile->getFamily() !== FamilyEnum::Image) {
            throw new \InvalidArgumentException('Input file must be an image');
        }
        $dpi = $options['dpi'];
            $this->logger->info(sprintf('Setting DPI to %s', $dpi));
            $writer = Writer::create(
                new Exiftool($this->logger)
            );
            $writer->write(
                $inputFile->getPath(),
                new MetadataBag([
//                    new Metadata([
//                        'dpi' => $dpi,
//                    ]),
                ]),
                null,
                [$dpi, $dpi]
            );

        return new OutputFile(
            $inputFile->getPath(),
            $inputFile->getType(),
            FamilyEnum::Image,
            false // TODO implement projection
        );
    }
}
