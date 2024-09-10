<?php

namespace Alchemy\RenditionFactory\DTO;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;

abstract readonly class BaseFile implements BaseFileInterface
{
    public function __construct(
        private string $path,
        private string $type,
        private FamilyEnum $family,
        protected ?MetadataContainerInterface $metadata = null,
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

    public function getMetadata(string $name): string|null
    {
        return $this->metadata?->getMetadata($name);
    }
}
