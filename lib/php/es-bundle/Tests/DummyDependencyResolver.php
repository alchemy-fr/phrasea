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
            $this->addParent(A::class, $object->getId());
            $this->appendDependencyIterator(B::class, array_map(fn (B $b) => [
                'id' => $b->getId(),
            ], $object->getChildren()));
        } elseif ($object instanceof B) {
            if ($object->getParent()) {
                $this->addDependency(A::class, $object->getParent()->getId());
            } elseif ($object->getNext()) {
                $this->addDependency(C::class, $object->getNext()->getId());
            }
        } elseif ($object instanceof C) {
            $this->addDependency(A::class, $object->getParent()->getId());
        }
    }
}
