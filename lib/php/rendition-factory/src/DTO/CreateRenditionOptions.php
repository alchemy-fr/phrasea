<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class CreateRenditionOptions
{
    public function __construct(
        private ?string $workingDirectory = null,
        private ?string $cacheDirectory = null,
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
}
