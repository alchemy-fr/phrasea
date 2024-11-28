<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class VoidTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'void';
    }

    public static function getDocumentation(): Documentation
    {
        static $doc = null;
        if (null === $doc) {
            $treeBuilder = new TreeBuilder('root');
            self::buildConfiguration($treeBuilder->getRootNode()->children());
            $doc = new Documentation(
                $treeBuilder,
                <<<HEADER
                **documentation to be done**.
                HEADER
            );
        }

        return $doc;
    }

    private static function buildConfiguration(NodeBuilder $builder): void
    {
        // todo
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        return $inputFile->createOutputFile();
    }
}
