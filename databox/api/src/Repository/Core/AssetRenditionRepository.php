<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetRendition;
use Doctrine\ORM\EntityRepository;

class AssetRenditionRepository extends EntityRepository
{
    public const OPT_DEFINITION_IDS = 'definitionIds';
    public const WITH_FILE = 'withFile';

    /**
     * @return AssetRendition[]
     */
    public function findAssetRenditions(string $assetId, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t')
            ->addSelect('s')
            ->innerJoin('t.definition', 's')
            ->andWhere('t.asset = :asset')
            ->setParameter('asset', $assetId)
            ->addOrderBy('s.priority', 'DESC');

        if ($options[self::OPT_DEFINITION_IDS] ?? false) {
            $queryBuilder
                ->andWhere('s.id IN (:def_ids)')
                ->setParameter('def_ids', $options['definitionIds']);
        }
        if (null !== ($options[self::WITH_FILE] ?? null)) {
            if ($options[self::WITH_FILE]) {

                $queryBuilder->andWhere('t.file IS NOT NULL');
            } else {
                $queryBuilder->andWhere('t.file IS  NULL');
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
