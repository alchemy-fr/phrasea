<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class CreateRenditionOptions
{
    public function __construct(
        private ?string $workingDirectory = null,
    )
    {
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }
}
