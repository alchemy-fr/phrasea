<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class VoidTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'void';
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = Documentation::createBaseTree(self::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            <<<HEADER
            A module that does nothing (testing purpose)
            HEADER
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        return $inputFile->createOutputFile();
    }
}
