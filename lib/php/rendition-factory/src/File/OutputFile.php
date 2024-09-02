<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\File;

class OutputFile {
    public function __construct(
        private string $type,
        private string $src,
    )
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSrc(): string
    {
        return $this->src;
    }
}
