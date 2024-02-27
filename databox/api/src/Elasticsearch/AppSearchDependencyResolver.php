<?php

namespace App\Elasticsearch;

use Alchemy\ESBundle\Indexer\SearchDependenciesResolverInterface;
use Alchemy\ESBundle\Indexer\SearchDependencyInterface;
use Alchemy\ESBundle\Indexer\SearchDependencyResolverTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;

class AppSearchDependencyResolver implements SearchDependenciesResolverInterface
{
    use SearchDependencyResolverTrait;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function updateDependencies(SearchDependencyInterface $object): void
    {
        if ($object instanceof Collection) {
            $this->appendDependencyIterator(
                Asset::class,
                $this->em->getRepository(Asset::class)
                    ->getCollectionAssetsIterator($object->getId())
            );
        } elseif ($object instanceof CollectionAsset) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        } elseif ($object instanceof Attribute) {
            $this->addDependency(Asset::class, $object->getAsset()->getId());
        }
    }
}
