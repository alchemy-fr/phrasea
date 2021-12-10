<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetRendition;
use Doctrine\ORM\EntityRepository;

class AssetRepresentationRepository extends EntityRepository
{
    /**
     * @return AssetRendition[]
     */
    public function findAssetRenditions(string $assetId): array
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->addSelect('s')
            ->innerJoin('t.definition', 's')
            ->andWhere('t.asset = :asset')
            ->setParameter('asset', $assetId)
            ->addOrderBy('s.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
