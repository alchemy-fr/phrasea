<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;

interface TransformerModuleInterface
{
    final public const TAG = 'alchemy_rendition_factory.transformer_module';

    public static function getName(): string;

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface;

    public static function getDocumentation(): Documentation;
}
