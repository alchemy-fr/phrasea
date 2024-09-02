<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\File;

final class InputFile extends OutputFile {
    private int|null $size = null;
    private ?array $metadata = null;

    public function __construct(
        string $type,
        string $src,
        private MetadataReader $metadataReader,
    )
    {
        parent::__construct($type, $src);
    }

    public function getFileSize(): int
    {
        return $this->size ?? ($this->size = filesize($this->getSrc()));
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? ($this->metadata = $this->metadataReader->read($this->getSrc()));
    }
}

