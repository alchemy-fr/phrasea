<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Alchemy\ESBundle\Indexer\IndexableDependenciesResolverInterface;
use Alchemy\ESBundle\Indexer\SearchDependencyResolverTrait;

class DummyDependencyResolver implements IndexableDependenciesResolverInterface
{
    use SearchDependencyResolverTrait;

    public function updateDependencies(ESIndexableDependencyInterface $object): void
    {
        if ($object instanceof A) {
            $this->addToParents(A::class, $object->getId());
            $this->appendDependencyIterator(B::class, array_map(fn (B $b) => [
                'id' => $b->getId()
            ], $object->getChildren()));
        } elseif ($object instanceof B) {
            $this->addDependency(A::class, $object->getParent()->getId());
        }
    }
}
