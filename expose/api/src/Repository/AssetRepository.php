<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    public function findBySlug(Publication $publication, string $assetSlug): ?Asset
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.publications', 'pa')
            ->andWhere('pa.publication = :p_id')
            ->andWhere('pa.slug = :slug')
            ->setParameter('p_id', $publication->getId())
            ->setParameter('slug', $assetSlug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }

    public function findAssetPublicationOwnedBy(Asset $asset, string $ownerId): ?Publication
    {
        return $this
            ->_em
            ->createQueryBuilder('p')
            ->select('p')
            ->from(Publication::class, 'p')
            ->innerJoin('p.assets', 'pa')
            ->andWhere('p.ownerId = :ownerId')
            ->andWhere('pa.asset = :id')
            ->setParameter('id', $asset->getId())
            ->setParameter('ownerId', $ownerId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
