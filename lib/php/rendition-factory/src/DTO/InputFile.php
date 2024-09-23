<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class InputFile extends BaseFile implements InputFileInterface
{
    public function createOutputFile(): OutputFileInterface
    {
        return new OutputFile($this->getPath(), $this->getType(), $this->getFamily());
    }
}
