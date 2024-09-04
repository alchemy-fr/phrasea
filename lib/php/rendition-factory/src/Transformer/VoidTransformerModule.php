<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\DTO\InputFile;
use Alchemy\RenditionFactory\DTO\OutputFile;

class VoidTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'void';
    }

    public function transform(InputFile $inputFile, array $options, TransformationContext $context): OutputFile
    {

        return OutputFile::fromInputFile($inputFile);
    }
}
