<?php

namespace Alchemy\RenditionFactory\DTO\Metadata;

final readonly class MetadataArray implements MetadataContainerInterface
{
    public function __construct(
        private array $metadata
    ) {
    }

    public function getMetadata(string $name): string|null
    {
        return $this->metadata[$name] ?? null;
    }
}
