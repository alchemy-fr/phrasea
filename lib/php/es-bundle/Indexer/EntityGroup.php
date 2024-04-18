<?php

namespace Alchemy\ESBundle\Indexer;

class EntityGroup
{
    private array $ids = [];

    public function add(string $id): void
    {
        $this->ids[$id] = 1;
    }

    public function has(string $id): bool
    {
        return isset($this->ids[$id]);
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
