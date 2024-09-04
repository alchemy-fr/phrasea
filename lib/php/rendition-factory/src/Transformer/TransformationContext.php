<?php

namespace Alchemy\RenditionFactory\Transformer;

final readonly class TransformationContext
{
    public function __construct(
        private string $workingDirectory,
    )
    {
    }

    public function createTmpFilePath(string $extension): string
    {
        $path = uniqid($this->workingDirectory.'/').'.'.$extension;

        if (file_exists($path)) {
            return $this->createTmpFilePath($extension);
        }

        return $path;
    }
}
