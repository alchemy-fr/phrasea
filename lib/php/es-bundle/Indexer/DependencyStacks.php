<?php

namespace Alchemy\ESBundle\Indexer;

class DependencyStacks
{
    private array $stacks = [];

    public function addStack(DependencyStack $stack): void
    {
        $this->stacks[] = $stack;
    }

    public function flush(): array
    {
        $stacks = $this->stacks;
        $this->stacks = [];

        return $stacks;
    }
}
