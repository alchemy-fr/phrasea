<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;

class VoidTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'Void';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFile
    {
        return $inputFile->createOutputFile();
    }
}
