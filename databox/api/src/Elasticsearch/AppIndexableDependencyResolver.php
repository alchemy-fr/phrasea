<?php

namespace App\Elasticsearch;

use Alchemy\ESBundle\Indexer\IndexableDependenciesResolverInterface;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
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

    public function updateDependencies(ESIndexableDependencyInterface $object): void
    {
        if ($object instanceof Collection) {
            $this->appendDependencyIterator(
                Asset::class,
                $this->em->getRepository(Asset::class)
                    ->getCollectionAssetIdsIterator($object->getId())
            );
        } elseif ($object instanceof CollectionAsset) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        } elseif ($object instanceof Attribute) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        } elseif ($object instanceof Asset) {
            $this->addToParents($object::class, $object->getId());

            $this->appendDependencyIterator(
                Attribute::class,
                $this->em->getRepository(Attribute::class)
                    ->getAssetAttributeIdsIterator($object->getId())
            );
        }
    }
}
