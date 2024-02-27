<?php

namespace Alchemy\ESBundle\Indexer;

trait SearchDependencyResolverTrait
{
    private \Closure $addToParentsClosure;
    private \Closure $addDependencyClosure;

    public function setAddToParentsClosure(\Closure $closure): void
    {
        $this->addToParentsClosure = $closure;
    }

    public function setAddDependencyClosure(\Closure $closure): void
    {
        $this->addDependencyClosure = $closure;
    }

    public function addToParents(string $class, string $id): void
    {
        $closure = $this->addToParentsClosure;
        $closure($class, $id);
    }

    public function addDependency(string $class, string $id): void
    {
        $closure = $this->addDependencyClosure;
        $closure($class, $id);
    }

    protected function appendDependencyIterator(string $class, iterable $iterator): void
    {
        foreach ($iterator as $row) {
            $this->addDependency($class, $row['id']);
        }
    }
}
