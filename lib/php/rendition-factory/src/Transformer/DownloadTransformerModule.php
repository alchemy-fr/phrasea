<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class DownloadTransformerModule implements TransformerModuleInterface
{
    public function __construct(private FileFamilyGuesser $fileFamilyGuesser)
    {
    }

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
            Download a file to be used as output.
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
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $path = $context->getRemoteFile($options['url']);
        $mimeType = $context->guessMimeTypeFromPath($path);
        $family = $this->fileFamilyGuesser->getFamily($path, $mimeType);

        return new OutputFile(
            $path,
            $mimeType,
            $family,
            false
        );
    }
}
