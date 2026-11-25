<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class VoidTransformerModule implements TransformerModuleInterface
{
    public const string NAME = 'void';

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
            Leaves the input file unchanged: the rendition is the input file itself (e.g. a file already readable by a web browser)
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // No option, but the validated transformation always carries an "options" key
        $builder->arrayNode('options')->end();
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        return $inputFile->createOutputFile();
    }
}
