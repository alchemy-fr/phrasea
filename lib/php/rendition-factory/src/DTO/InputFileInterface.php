<?php

namespace Alchemy\RenditionFactory\DTO;

interface InputFileInterface extends BaseFileInterface
{
    public function createOutputFile(): OutputFileInterface;
}
