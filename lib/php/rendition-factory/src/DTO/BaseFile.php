<?php

namespace Alchemy\RenditionFactory\DTO;

abstract readonly class BaseFile
{
    public function __construct(
        private string $path,
        private string $type,
        private FamilyEnum $family,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFamily(): FamilyEnum
    {
        return $this->family;
    }
}
