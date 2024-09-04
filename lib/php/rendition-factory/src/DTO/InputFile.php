<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class InputFile extends BaseFile
{
    public static function fromOutputFile(OutputFile $outputFile): self
    {
        return new self($outputFile->getPath(), $outputFile->getType(), $outputFile->getFamily());
    }
}
