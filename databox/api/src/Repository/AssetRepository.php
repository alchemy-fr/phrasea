<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Core\Asset;
use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    /**
     * @return Asset[]
     */
    public function getCollectionAssets(string $collectionId): array
    {
        return $this
            ->createQueryBuilder('a')
            ->innerJoin('a.collections', 'ac')
            ->andWhere('ac.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->getResult();
    }
}
