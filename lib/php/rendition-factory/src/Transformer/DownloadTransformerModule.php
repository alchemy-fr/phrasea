<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerConfigHelper;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DownloadTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'download';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = TransformerConfigHelper::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            Download a file to be used as "substitution" output.
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('options')
                ->children()
                    ->scalarNode('url')
                        ->info('url of the file to download')
                    ->end()
                    ->scalarNode('family')
                        ->info('family of the output file (use "image" | "animation" | "video" | "audio" | "document" | "unknown", according of the downloaded file)')
                        ->defaultValue('image')
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $path = $context->getRemoteFile($options['url']);

        return new OutputFile(
            $path,
            $context->guessMimeTypeFromPath($path),
            FamilyEnum::tryFrom($options['family']),
            false
        );
    }
}
