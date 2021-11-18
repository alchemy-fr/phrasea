<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\SubDefinition;
use Doctrine\ORM\EntityRepository;

class SubDefinitionRepository extends EntityRepository
{
    /**
     * @return SubDefinition[]
     */
    public function findAssetSubDefs(string $assetId): array
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->addSelect('s')
            ->innerJoin('t.specification', 's')
            ->andWhere('t.asset = :asset')
            ->setParameter('asset', $assetId)
            ->addOrderBy('s.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
