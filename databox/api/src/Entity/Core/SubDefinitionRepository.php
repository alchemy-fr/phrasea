<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\EntityRepository;

class SubDefinitionRepository extends EntityRepository
{
    public function findSubDefByType(string $assetId, string $type): ?SubDefinition
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->innerJoin('t.specification', 's')
            ->andWhere('t.asset = :asset')
            ->andWhere('s.useAs'.ucfirst($type).' = true')
            ->setParameter('asset', $assetId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
