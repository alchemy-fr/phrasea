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
}
