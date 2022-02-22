<?php

declare(strict_types=1);

namespace App\Repository\Core;

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

    /**
     * @return Asset[]
     */
    public function findByKey(string $key, string $workspaceId): ?Asset
    {
        return $this->findOneBy([
            'key' => $key,
            'workspace' => $workspaceId,
        ]);
    }
}
