<?php

namespace Alchemy\RenditionFactory\Context;

final class BuildHashes
{
    final public const PATH_LEVEL_FAMILY = 0;
    final public const PATH_LEVEL_MODULE = 1;

    private array $path = [];
    private array $buildHashes = [];

    public function setPath(int $depth, string|int $key): void
    {
        $this->path = array_slice($this->path, 0, $depth);
        $this->path[] = $key;
    }

    public function addHash(string $hash): void
    {
        $this->buildHashes[] = [...$this->path, $hash];
    }

    public function getHashes(): array
    {
        return $this->buildHashes;
    }
}
