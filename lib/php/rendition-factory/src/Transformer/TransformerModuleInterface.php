<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface TransformerModuleInterface
{
    final public const TAG = 'alchemy_rendition_factory.transformer_module';

    public static function getName(): string;

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface;

//    public function buildConfiguration(NodeBuilder $builder): void;
//    public function getExtraConfigurationBuilders(): iterable;
}
