<?php

namespace Alchemy\RenditionFactory\DTO;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;

abstract readonly class BaseFile implements BaseFileInterface
{
    private array $pi;

    public function __construct(
        private string $path,
        private string $type,
        private FamilyEnum $family,
        protected ?MetadataContainerInterface $metadata = null,
    ) {
        $this->pi = pathinfo($path);
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

    public function getExtension(): string
    {
        return $this->pi['extension'];
    }
}
