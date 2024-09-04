<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\OutputFile;

interface TransformerModuleInterface
{
    final public const TAG = 'alchemy_rendition_factory.transformer_module';

    public static function getName(): string;

    public function transform(InputFile $inputFile, array $options, TransformationContext $context): OutputFile;
}
