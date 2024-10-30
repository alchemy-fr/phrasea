<?php

namespace Alchemy\RenditionFactory\DTO;

final readonly class OutputFile extends BaseFile implements OutputFileInterface
{
    public function __construct(
        string $path,
        string $type,
        FamilyEnum $family,
        private bool $projection,
        private ?array $buildHashes = null,
    ) {
        parent::__construct($path, $type, $family);
    }

    public function createNextInputFile(): InputFileInterface
    {
        return new InputFile($this->getPath(), $this->getType(), $this->getFamily());
    }

    public function getBuildHashes(): ?array
    {
        return $this->buildHashes;
    }

    public function withBuildHashes(?array $buildHashes): OutputFileInterface
    {
        return new self($this->getPath(), $this->getType(), $this->getFamily(), $this->isProjection(), $buildHashes);
    }

    public function withProjection(bool $isProjection): OutputFileInterface
    {
        return new self($this->getPath(), $this->getType(), $this->getFamily(), $isProjection, $this->getBuildHashes());
    }

    public function isProjection(): bool
    {
        return $this->projection;
    }
}
