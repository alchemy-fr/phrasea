<?php

namespace Alchemy\ESBundle\Indexer;

trait SearchDependencyResolverTrait
{
    private DependencyStack $dependencyStack;

    public function setDependencyStack(DependencyStack $dependencyStack): void
    {
        $this->dependencyStack = $dependencyStack;
    }

    public function addParent(string $class, string $id): void
    {
        $this->dependencyStack->addParent($class, $id);
    }

    public function addDependency(string $class, string $id): void
    {
        $this->dependencyStack = $this->dependencyStack->addDependency($class, $id);
    }

    protected function appendDependencyIterator(string $class, iterable $iterator): void
    {
        foreach ($iterator as $row) {
            $this->addDependency($class, $row['id']);
        }
    }
}
