<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;

class VoidTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'void';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContext $context): OutputFile
    {
        return OutputFile::fromInputFile($inputFile);
    }
}
