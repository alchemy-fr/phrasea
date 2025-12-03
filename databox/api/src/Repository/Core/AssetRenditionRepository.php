<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityRepository;

class AssetRenditionRepository extends EntityRepository
{
    final public const string OPT_DEFINITION_IDS = 'definitionIds';
    final public const string OPT_USED_AS = 'usedAs';
    final public const string WITH_FILE = 'withFile';

    /**
     * @return AssetRendition[]
     */
    public function findAssetRenditions(string $assetId, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t, f')
            ->addSelect('s')
            ->innerJoin('t.definition', 's')
            ->leftJoin('t.file', 'f')
            ->andWhere('t.asset = :asset')
            ->setParameter('asset', $assetId)
            ->addOrderBy('s.priority', 'DESC');

        if ($options[self::OPT_DEFINITION_IDS] ?? false) {
            $queryBuilder
                ->andWhere('s.id IN (:def_ids)')
                ->setParameter('def_ids', $options[self::OPT_DEFINITION_IDS]);
        }
        if ($options[self::OPT_USED_AS] ?? false) {
            $queryBuilder
                ->andWhere(implode(' OR ', array_map(
                    fn (string $usedAs): string => 's.useAs'.ucfirst($usedAs).' = true',
                    RenditionDefinition::BUILT_IN_RENDITIONS
                )));
        }

        if (null !== ($options[self::WITH_FILE] ?? null)) {
            if ($options[self::WITH_FILE]) {
                $queryBuilder->andWhere('t.file IS NOT NULL');
            } else {
                $queryBuilder->andWhere('t.file IS NULL');
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
