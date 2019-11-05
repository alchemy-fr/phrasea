<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubDefinition;
use Doctrine\ORM\EntityRepository;

class SubDefinitionRepository extends EntityRepository
{
    public function findSubDefinitionByType(string $assetId, string $type): ?SubDefinition
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.asset = :asset_id')
            ->andWhere('d.name = :type')
            ->setParameter('asset_id', $assetId)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
