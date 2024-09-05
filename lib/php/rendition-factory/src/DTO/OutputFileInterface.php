<?php

namespace Alchemy\RenditionFactory\DTO;

interface OutputFileInterface extends BaseFileInterface
{
    public function createNextInputFile(): InputFileInterface;
}
