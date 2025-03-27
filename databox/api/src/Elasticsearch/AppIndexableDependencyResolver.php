<?php

namespace App\Elasticsearch;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Alchemy\ESBundle\Indexer\IndexableDependenciesResolverInterface;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchDependencyResolverTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;

class AppIndexableDependencyResolver implements IndexableDependenciesResolverInterface
{
    use SearchDependencyResolverTrait;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function updateDependencies(ESIndexableDependencyInterface $object, Operation $operation): void
    {
        if ($object instanceof Collection) {
            if (Operation::Insert === $operation && null !== $object->getParent()) {
                $this->addDependency(Collection::class, $object->getParent()->getId());
            }
        } elseif ($object instanceof CollectionAsset) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        } elseif ($object instanceof Attribute) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        }
    }
}
