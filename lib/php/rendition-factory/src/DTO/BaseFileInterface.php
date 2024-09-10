<?php

namespace Alchemy\RenditionFactory\DTO;

interface BaseFileInterface
{
    public function getPath(): string;

    public function getType(): string;

    public function getFamily(): FamilyEnum;

    public function getMetadata(string $name): string|null;
}
