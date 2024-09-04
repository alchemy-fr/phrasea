<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class OutputFile extends BaseFile
{
    public static function fromInputFile(InputFile $inputFile): self
    {
        return new self($inputFile->getPath(), $inputFile->getType(), $inputFile->getFamily());
    }
}
