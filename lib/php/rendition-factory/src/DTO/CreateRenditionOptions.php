<?php

namespace Alchemy\RenditionFactory\DTO;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;

final readonly class CreateRenditionOptions
{
    public function __construct(
        private ?string $workingDirectory = null,
        private ?string $cacheDirectory = null,
        private ?MetadataContainerInterface $metadataContainer = null,
    )
    {
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function getCacheDirectory(): ?string
    {
        return $this->cacheDirectory;
    }

    public function getMetadataContainer(): ?MetadataContainerInterface
    {
        return $this->metadataContainer;
    }
}
