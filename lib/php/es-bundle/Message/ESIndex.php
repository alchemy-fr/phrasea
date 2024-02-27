<?php

namespace Alchemy\ESBundle\Message;

final readonly class ESIndex
{
    public function __construct(
        private array $objects,
        private int $depth = 1,
        private array $parents = [],
    ) {
    }

    public function getObjects(): array
    {
        return $this->objects;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getParents(): array
    {
        return $this->parents;
    }
}
