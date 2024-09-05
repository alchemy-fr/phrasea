<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class OutputFile extends BaseFile implements OutputFileInterface
{
    public function createNextInputFile(): InputFileInterface
    {
        return new InputFile($this->getPath(), $this->getType(), $this->getFamily());
    }
}
